<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\CPE\Dictionary;
use CheckCpe\CPE\Status;
use CheckCpe\Generators\MarkdownGenerator;
use CheckCpe\Generators\WeightedMarkdownGenerator;
use CheckCpe\Util\Logger;

class Runner
{
    /**
     * @var array<string,Port>
     */
    protected array $allports = [];

    public function run(): bool
    {
        Logger::info('Searching Ports ...');

        $origins = [];
        $categories = new \FilesystemIterator(Config::getPortsDir());

        foreach ($categories as $category) {
            if (is_string($category) || !$category->isDir()) {
                continue;
            }

            if (in_array($category->getFilename(), ['Mk', 'Templates', 'Tools', 'distfiles', 'packages', '.git'])) {
                continue;
            }

            $ports = new \FilesystemIterator($category->getPathname());

            foreach ($ports as $portname) {
                if (is_string($portname) || !$portname->isDir()) {
                    continue;
                }

                $origins[] = $category->getFilename().'/'.$portname->getFilename();
            }
        }

        Logger::info('Found '.count($origins).' ports');

        // Scanning ports (parallel)
        $pool = new \Amp\Parallel\Worker\DefaultPool(8);

        try {
            $ports = \Amp\Promise\wait(\Amp\ParallelFunctions\parallelMap($origins, function ($origin) {
                return new Port($origin);
            }, $pool));

            foreach ($ports as $port) {
                $this->allports[$port->getOrigin()] = $port;
            }

            unset($ports);
            ksort($this->allports);
        } catch (\Amp\MultiReasonException $e) {
            foreach ($e->getReasons() as $r) {
                Logger::warning($r->getMessage());
            }
        }

        Logger::info('Scanned '.count($this->allports).' ports');

        Logger::info('Comparing with CPE Dictionary ...');

        $dictionary = new Dictionary(Config::getDbHandle());
        $addmatch = Config::getAddMatchData();
        $falsematch = Config::getFalseMatchData();

        foreach ($this->allports as $port) {
            if ($port->getCPEStr() != '') {
                $product = $dictionary->findProduct($port->getCPEVendor(), $port->getCPEProduct());
                if ($product === null) {
                    if (isset($addmatch[$port->getOrigin()]) &&
                        $addmatch[$port->getOrigin()] == $port->getCPEVendor().':'.$port->getCPEProduct()) {
                        Logger::info('Validated CPE for '.$port->getOrigin().' via local overwrite');
                        $port->setCPEStatus(Status::VALID);
                    } else {
                        $port->setCPEStatus(Status::INVALID);
                    }
                } else {
                    $port->setCPEStatus(Status::VALID);
                }
            } else {
                foreach ($dictionary->findProductsByProductname($port->getPortname()) as $product) {
                    if (isset($falsematch[$port->getOrigin()])) {
                        if (in_array($product, $falsematch[$port->getOrigin()])) {
                            Logger::info('Ignoring false match for '.$port->getOrigin());
                            continue;
                        }
                    }

                    $port->addCPECandidate($product);
                    $port->setCPEStatus(Status::MISSING);
                }
            }
        }

        Logger::info('Generating Markdown Reports ...');

        $generators = [];
        $generators[Status::VALID] = new MarkdownGenerator();
        $generators[Status::INVALID] = new MarkdownGenerator();
        $generators[Status::MISSING] = new MarkdownGenerator();
        $generators[Status::UNKNOWN] = new MarkdownGenerator();

        $generators['easy'] = new WeightedMarkdownGenerator('Easy', Config::getPriorityData());
        $generators['important'] = new WeightedMarkdownGenerator('Important Ports', Config::getPriorityData());

        foreach ($this->allports as $port) {
            // Status
            if (isset($generators[$port->getCPEStatus()])) {
                $generators[$port->getCPEStatus()]->addPort($port);
            }

            // Easy
            if ($port->getCPEStatus() == Status::MISSING) {
                $candidates = $port->getCPECandidates();
                if (count($candidates) == 1) {
                    if ($candidates[0]->getVendor() == $candidates[0]->getProduct()) {
                        $generators['easy']->addPort($port);
                    }
                }
            }

            // Important
            if ($port->getCPEStatus() == Status::INVALID || $port->getCPEStatus() == Status::MISSING) {
                $generators['important']->addPort($port);
            }
        }

        foreach ($generators as $status => $generator) {
            $file = Config::getLogsDir().'/'.$status.'.md';

            Logger::info('Generating '.$file.' ...');
            $generator->toFile($file);
        }

        Logger::info('Generating env file ...');

        $env = '';
        foreach ($generators as $status => $generator) {
            $env .= sprintf("%s=%d\n", strtoupper($status), $generator->count());
        }

        if (file_put_contents(Config::getLogsDir().'/env', $env) === false) {
            return false;
        }

        return true;
    }
}

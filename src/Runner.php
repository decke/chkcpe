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
    protected array $allports;

    public function run(): bool
    {
        $cnt = 0;
        $failed = 0;
        Logger::info('Scanning Portstree ...');

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

                $origin = $category->getFilename().'/'.$portname->getFilename();
                try {
                    $cnt++;
                    $this->allports[$origin] = new Port($origin);

                    if ($cnt % 1000 == 0) {
                        Logger::info('Scanned '.$cnt.' ports');
                    }
                } catch (\Exception $e) {
                    Logger::error($e->getMessage());
                    $failed++;
                }
            }
        }

        Logger::info('Scanned '.$cnt.' ports');
        Logger::info('Failed to scan '.$failed.' ports');

        ksort($this->allports);

        $cnt = 0;
        Logger::info('Comparing with CPE Dictionary ...');

        $dictionary = new Dictionary(Config::getDbHandle());

        foreach ($this->allports as $port) {
            if ($port->getCPEStr() != '') {
                $product = $dictionary->findProduct($port->getCPEVendor(), $port->getCPEProduct());
                if ($product === null) {
                    $port->setCPEStatus(Status::INVALID);
                } else {
                    $port->setCPEStatus(Status::VALID);
                }
            } else {
                foreach ($dictionary->findProductsByProductname($port->getPortname()) as $product) {
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
                    $generators['easy']->addPort($port);
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

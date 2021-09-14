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
        Logger::info('Finding ports ...');

        $cnt = 0;
        $origins = '';
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

                $origins .= $category->getFilename().'/'.$portname->getFilename()."\n";
                $cnt++;
            }
        }

        Logger::info('Found '.$cnt.' ports');

        $tmpfile = tempnam('/tmp', 'chkcpe');
        if ($tmpfile === false) {
            throw new \Exception('Could not create tempfile');
        }

        file_put_contents($tmpfile, $origins);

        // Parallel scanning
        $cnt = 0;
        $portsdir = Config::getPortsDir();

        $cmd = sprintf('parallel %s -C %s/{} -V.CURDIR -VPORTNAME -VCPE_VERSION -VMAINTAINER -VCPE_URI :::: %s', Config::getMakeBin(), $portsdir, $tmpfile);
        $fp = popen($cmd, 'r');
        while ($fp != null && !feof($fp)) {
            $line = fread($fp, 4096);
            if (!$line) {
                Logger::info('Skipping invalid output');
                continue;
            }

            $parts = explode("\n", $line);
            if (count($parts) != 6) {
                Logger::info('Skipping invalid output');
                continue;
            }

            $parts[0] = substr($parts[0], strlen($portsdir)+1);

            try {
                $this->allports[$parts[0]] = new Port($parts[0], $parts[1], $parts[2], $parts[3], $parts[4]);
            } catch (\Exception $e) {
                Logger::warning($e->getMessage());
            }

            if (++$cnt % 1000 == 0) {
                Logger::info('Scanned '.$cnt.' ports');
            }
        }

        ksort($this->allports);

        Logger::info('Scanned '.count($this->allports).' ports');

        Logger::info('Comparing with CPE Dictionary ...');

        $dictionary = new Dictionary(Config::getDbHandle());
        $addmatch = Config::getAddMatchData();
        $falsematch = Config::getFalseMatchData();

        foreach ($this->allports as $port) {
            if ($port->getCPEStr() != '') {
                $product = $dictionary->findProduct($port->getCPEVendor(), $port->getCPEProduct());
                if ($product != null) {
                    $port->setCPE($product);
                }

                if ($product === null) {
                    if (isset($addmatch[$port->getOrigin()]) &&
                        $addmatch[$port->getOrigin()] == $port->getCPEVendor().':'.$port->getCPEProduct()) {
                        Logger::info('Validated CPE for '.$port->getOrigin().' via local overwrite');
                        $port->setCPEStatus(Status::VALID);
                    } else {
                        $port->setCPEStatus(Status::INVALID);
                    }
                } elseif ($product->isDeprecated()) {
                    $port->setCPEStatus(Status::DEPRECATED);
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
                    $port->setCPEStatus(Status::CHECKNEEDED);
                }
            }
        }

        Logger::info('Generating Markdown Reports ...');

        $generators = [];
        $generators[Status::VALID] = new MarkdownGenerator();
        $generators[Status::DEPRECATED] = new MarkdownGenerator();
        $generators[Status::INVALID] = new MarkdownGenerator();
        $generators[Status::CHECKNEEDED] = new MarkdownGenerator();
        $generators[Status::UNKNOWN] = new MarkdownGenerator();

        $generators['important'] = new WeightedMarkdownGenerator('Important Ports', Config::getPriorityData());

        foreach ($this->allports as $port) {
            // Status
            if (isset($generators[$port->getCPEStatus()])) {
                $generators[$port->getCPEStatus()]->addPort($port);
            }

            // Important
            switch ($port->getCPEStatus()) {
                case Status::DEPRECATED:
                case Status::INVALID:
                case Status::CHECKNEEDED:
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

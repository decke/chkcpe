<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\CPE\Dictionary;
use CheckCpe\CPE\Product;
use CheckCpe\CPE\Status;
use CheckCpe\Generators\MarkdownGenerator;
use CheckCpe\Generators\WeightedMarkdownGenerator;
use CheckCpe\Util\Logger;

class Runner
{
    protected \PDO $handle;

    public function __construct()
    {
        $this->handle = Config::getDbHandle();
    }

    public function loadCPEData(): bool
    {
        $xml = simplexml_load_file(Config::getCPEDictionary());

        if ($xml === false) {
            throw new \Exception('Loading CPE Dictionary failed');
        }

        $this->handle->exec('DELETE FROM cpes');
        $this->handle->exec('DELETE FROM products');
        $this->handle->exec('VACUUM;');
        $this->handle->beginTransaction();

        $dictionary = new Dictionary($this->handle);

        $cnt = 0;

        foreach ($xml->{'cpe-item'} as $cpe) {
            /*
              <cpe-item name="cpe:/a:xiph:libvorbis:1.3.6" deprecated="true" deprecation_date="2019-10-17T15:10:18.580Z">
                <title xml:lang="en-US">Xiph Libvorbis 1.3.6</title>
                <references>
                  <reference href="https://xiph.org/downloads/">Product</reference>
                </references>
                <cpe-23:cpe23-item name="cpe:2.3:a:xiph:libvorbis:1.3.6:*:*:*:*:*:*:*">
                  <cpe-23:deprecation date="2019-10-17T11:10:18.580-04:00">
                    <cpe-23:deprecated-by name="cpe:2.3:a:xiph.org:libvorbis:1.3.6:*:*:*:*:*:*:*" type="NAME_CORRECTION"/>
                  </cpe-23:deprecation>
                </cpe-23:cpe23-item>
              </cpe-item>
            */

            $cpe_title = $cpe->title;
            $cpe_deprecated = $cpe->attributes(null)->deprecated;
            $cpe_fs = (string)$cpe->children('cpe-23', true)->{'cpe23-item'}->attributes(null)->name;

            try {
                $product = new Product($cpe_fs);

                if ($product->getPart() != 'a') {
                    continue;
                }

                if ($cpe_deprecated == 'true') {
                    $cpe_deprecated_by = (string)$cpe->children('cpe-23', true)->{'cpe23-item'}->{'deprecation'}->
                        {'deprecated-by'}->attributes(null)->name;

                    $product->setDeprecatedBy(new Product($cpe_deprecated_by));
                }

                if (!$dictionary->addProduct($product)) {
                    Logger::warning('Could not add Product '.$cpe_fs);
                    continue;
                }
            } catch (\Exception $e) {
                Logger::warning('Could not process CPE entry: '.$cpe_fs.' because '.$e->getMessage());
            }

            if (++$cnt % 10000 == 0) {
                Logger::info('Added '.$cnt.' CPE entries');
                $this->handle->commit();
                $this->handle->beginTransaction();
            }
        }

        $this->handle->commit();

        return true;
    }

    public function findPorts(): bool
    {
        $this->handle->exec('DELETE FROM ports');
        $this->handle->exec('DELETE FROM candidates');
        $this->handle->exec('VACUUM;');
        $this->handle->beginTransaction();

        $stmt = $this->handle->prepare('INSERT INTO ports (origin, category, portdir, status) VALUES (?, ?, ?, ?)');

        Logger::info('Finding ports ...');

        $cnt = 0;
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

                if (!$stmt->execute([$origin, $category->getFilename(), $portname->getFilename(), Status::NEW])) {
                    throw new \Exception('DB Error');
                }

                $cnt++;
            }
        }

        $this->handle->commit();

        Logger::info('Found '.$cnt.' ports');

        return true;
    }

    public function scanPorts(): bool
    {
        $this->handle->beginTransaction();
        $stmt = $this->handle->prepare('UPDATE ports SET portname = ?, version = ?, maintainer = ?, cpeuri = ?, status = ? WHERE origin = ?');

        // write list of origins to tmpfile
        $origins = join("\n", $this->listPorts(Status::NEW));

        $tmpfile = tempnam('/tmp', 'chkcpe');
        if ($tmpfile === false) {
            throw new \Exception('Could not create tempfile');
        }

        file_put_contents($tmpfile, $origins);

        // Parallel scanning
        $cnt = 0;
        $portsdir = Config::getPortsDir();

        $cmd = sprintf('parallel %s -C %s/{} -V.CURDIR -VPORTNAME -VPORTVERSION -VMAINTAINER -VCPE_STR :::: %s', Config::getMakeBin(), $portsdir, $tmpfile);
        $fp = popen($cmd, 'r');
        while ($fp != null && !feof($fp)) {
            $line = fread($fp, 4096);
            if (!$line) {
                Logger::info('Skipping invalid output');
                continue;
            }

            $parts = explode("\n", $line);
            if (count($parts) != 6) {
                Logger::info('Skipping invalid output for directory '.$parts[0].' ('.count($parts).' lines, expected 6)');
                continue;
            }

            $parts[0] = substr($parts[0], strlen($portsdir)+1);

            try {
                if (!$stmt->execute([$parts[1], $parts[2], $parts[3], $parts[4], Status::SCANNED, $parts[0]])) {
                    throw new \Exception('DB Error');
                }
            } catch (\Exception $e) {
                Logger::warning($e->getMessage());
            }

            if (++$cnt % 1000 == 0) {
                Logger::info('Scanned '.$cnt.' ports');
            }
        }

        $this->handle->commit();

        Logger::info('Scanned '.$cnt.' ports');

        return true;
    }

    public function comparePortsWithDictionary(): bool
    {
        $this->handle->beginTransaction();

        Logger::info('Comparing with CPE Dictionary ...');

        $dictionary = new Dictionary(Config::getDbHandle());
        $overlay = Config::getOverlay();

        foreach ($this->loadPorts(Status::SCANNED) as $port) {
            $port->setCPEStatus(Status::UNKNOWN);

            if ($port->getCPEStr() != '') {
                $product = $dictionary->findProduct($port->getCPEVendor(), $port->getCPEProduct());
                if ($product != null) {
                    $port->setCPE($product);
                }

                if ($product === null) {
                    if ($overlay->exists($port->getOrigin(), 'custommatch')) {
                        $product = new Product($overlay->get($port->getOrigin(), 'custommatch'));
                        $port->setCPE($product);

                        $port->setCPEStatus(Status::VALID);
                    } else {
                        $port->setCPEStatus(Status::INVALID);
                    }
                } elseif ($product->isDeprecated()) {
                    $port->setCPEStatus(Status::DEPRECATED);
                } else {
                    $port->setCPEStatus(Status::VALID);
                }
            } else if ($overlay->exists($port->getOrigin(), 'confirmedmatch')) {
                foreach ($port->getCPECandidates() as $candidate) {
                    $port->removeCPECandidate($candidate);
                }

                $product = new Product($overlay->get($port->getOrigin(), 'confirmedmatch'));
                $port->addCPECandidate($product);

                $port->setCPEStatus(Status::READYTOCOMMIT);

                if ($product->isDeprecated()) {
                    $port->setCPEStatus(Status::DEPRECATED);
                }
            } else {
                $nomatch = [];
                if ($overlay->exists($port->getOrigin(), 'nomatch')) {
                    foreach ($overlay->get($port->getOrigin(), 'nomatch') as $cpe) {
                        try {
                            $nomatch[] = new Product($cpe);
                        } catch (\Exception $e) {
                            Logger::warning('Invalid nomatch CPE string for port '.$port->getOrigin().' : '.$cpe);
                        }
                    }
                }

                foreach ($dictionary->findProductsByProductname($port->getPortname()) as $product) {
                    foreach ($nomatch as $prod) {
                        if ($prod->compareTo($product)) {
                            Logger::info('Ignoring false match for '.$port->getOrigin());
                            continue 2;
                        }
                    }

                    $port->addCPECandidate($product);
                    $port->setCPEStatus(Status::CHECKNEEDED);
                }
            }

            $port->saveToDB();
        }

        $this->handle->commit();

        return true;
    }

    public function generateReports(): bool
    {
        Logger::info('Generating Markdown Reports ...');

        $generators = [];
        $generators[Status::VALID] = new MarkdownGenerator();
        $generators[Status::INVALID] = new MarkdownGenerator();
        $generators[Status::DEPRECATED] = new MarkdownGenerator();
        $generators[Status::CHECKNEEDED] = new WeightedMarkdownGenerator();
        $generators[Status::READYTOCOMMIT] = new MarkdownGenerator();
        $generators[Status::UNKNOWN] = new MarkdownGenerator();

        foreach ($this->loadPorts() as $port) {
            if (isset($generators[$port->getCPEStatus()])) {
                $generators[$port->getCPEStatus()]->addPort($port);
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

    public function run(): bool
    {
        if (!$this->loadCPEData()) {
            Logger::error('Loading CPE Dictionary failed');
            return false;
        }

        if (!$this->findPorts()) {
            Logger::error('Finding ports failed');
            return false;
        }

        if (!$this->scanPorts()) {
            Logger::error('Scanning ports failed');
            return false;
        }

        // retry scanning for the ports that failed at first attempt
        if (!$this->scanPorts()) {
            Logger::error('Scanning ports failed');
            return false;
        }

        if (!$this->comparePortsWithDictionary()) {
            Logger::error('Comparing ports with CPE Dictionary failed');
            return false;
        }

        if (!$this->generateReports()) {
            Logger::error('Generating Reports failed');
            return false;
        }

        return true;
    }

    /**
     * @return array<string,Port>
     */
    public function loadPorts(string $status = '', string $category = ''): array
    {
        $ports = [];

        foreach ($this->listPorts($status, $category) as $origin) {
            try {
                $port = Port::loadFromDb($origin);

                if ($port === null) {
                    continue;
                }

                $ports[$origin] = $port;
            } catch (\TypeError) {
                ;
            } catch (\Exception $e) {
                Logger::warning('Ignoring port '.$origin.' because of '.$e->getMessage());
            }
        }

        return $ports;
    }

    /**
     * @return array<string>
     */
    public function listPorts(string $status = '', string $category = ''): array
    {
        $stmt = $this->handle->prepare('SELECT origin FROM ports WHERE (status = ? OR ? = \'\') AND (category = ? OR ? = \'\') ORDER BY origin');

        if (!$stmt->execute([$status, $status, $category, $category])) {
            throw new \Exception('DB Error');
        }

        $ports = [];

        while ($row = $stmt->fetchObject()) {
            $ports[] = (string)$row->origin;
        }

        return $ports;
    }
}

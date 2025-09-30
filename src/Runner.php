<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\CPE\Dictionary;
use CheckCpe\CPE\Product;
use CheckCpe\CPE\Status;
use CheckCpe\Generators\MarkdownGenerator;
use CheckCpe\Util\Logger;
use PacificSec\CPE\Common\WellFormedName;

class Runner
{
    protected \PDO $handle;

    public function __construct()
    {
        $this->handle = Config::getDbHandle();
    }

    public function loadCPEData(): bool
    {
        $files = glob(Config::getCPEDictionary());

        if ($files === false) {
            throw new \Exception('Loading CPE Dictionary failed');
        }

        $this->handle->exec('DELETE FROM cpes');
        $this->handle->exec('DELETE FROM products');
        $this->handle->exec('VACUUM;');
        $this->handle->beginTransaction();

        $dictionary = new Dictionary($this->handle);

        foreach($files as $file) {
            $raw = file_get_contents($file);

            if ($raw === false) {
                throw new \Exception('Loading CPE Dictionary file '.$file.' failed');
            }

            $json = json_decode($raw);
            unset($raw);

            if ($json === null) {
                throw new \Exception('Parsing CPE Dictionary file '.$file.' failed');
	    }

            $cnt = 0;

            foreach ($json as $cpe) {
                /*
                  "cpe" : {
                    "cpeName" : "cpe:2.3:a:cjson_project:cjson:1.7.17:*:*:*:*:*:*:*",
                    "cpeNameId" : "51565535-1A24-4360-8549-49615C94076B",
                    "created" : "2025-07-22T18:17:44.500",
                    "deprecated" : true,
                    "deprecatedBy" : [
                       {
                              "cpeName" : "cpe:2.3:a:davegamble:cjson:1.7.17:*:*:*:*:*:*:*",
                              "cpeNameId" : "49D5BD61-5E58-4EDD-BA50-6D381F385448"
                       }
                    ],
                    "lastModified" : "2025-07-22T18:19:00.813",
                    "refs" : [
                       {
                              "ref" : "https://github.com/DaveGamble/cJSON",
                              "type" : "Project"
                       },
                       {
                              "ref" : "https://github.com/DaveGamble/cJSON/releases/tag/v0.0.0",
                              "type" : "Vendor"
                       }
                    ],
                    "titles" : [
                       {
                              "lang" : "en",
                              "title" : "cJSON Project cJSON 1.7.17 "
                       }
                    ]
                  }
                */

                $cpe_title = $cpe->titles[0]->title;
                $cpe_deprecated = $cpe->deprecated;
                $cpe_fs = $cpe->cpeName;

                try {
                    $product = new Product($cpe_fs);

                    if ($product->getPart() != 'a') {
                        continue;
                    }

                    if ($cpe_deprecated) {
                        $cpe_deprecated_by = (string)$cpe->deprecatedBy[0]->cpeName;

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
        $stmt = $this->handle->prepare('UPDATE ports SET portname = ?, version = ?, maintainer = ?, cpeuri = ?, metaport = ?, status = ? WHERE origin = ?');

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

        $expected_lines = 7;
        $cmd = sprintf('parallel %s -C %s/{} -V.CURDIR -VPORTNAME -VPORTVERSION -VMAINTAINER -VCPE_STR -VUSES :::: %s', Config::getMakeBin(), $portsdir, $tmpfile);
        $fp = popen($cmd, 'r');
        while ($fp != null && !feof($fp)) {
            $line = fread($fp, 4096);
            if (!$line) {
                Logger::info('Skipping invalid output');
                continue;
            }

            $parts = explode("\n", $line);
            if (count($parts) != $expected_lines) {
                Logger::info('Skipping invalid output for directory '.$parts[0].' ('.count($parts).' lines, expected '.$expected_lines.')');
                continue;
            }

            $parts[0] = substr($parts[0], strlen($portsdir)+1);

            $metaport = 0;
            if (strpos($parts[5], 'metaport') !== false) {
                $metaport = 1;
            }

            try {
                if (!$stmt->execute([$parts[1], $parts[2], $parts[3], $parts[4], $metaport, Status::SCANNED, $parts[0]])) {
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
                } elseif ($port->isMetaport()) {
                    $port->setCPEStatus(Status::INVALID);
                } else {
                    $port->setCPEStatus(Status::VALID);
                }
            } elseif ($overlay->exists($port->getOrigin(), 'confirmedmatch')) {
                foreach ($port->getCPECandidates() as $candidate) {
                    $port->removeCPECandidate($candidate);
                }

                $product = new Product($overlay->get($port->getOrigin(), 'confirmedmatch'));
                $port->addCPECandidate($product);

                $port->setCPEStatus(Status::READYTOCOMMIT);

                if ($product->isDeprecated()) {
                    $port->setCPEStatus(Status::DEPRECATED);
                }
            } elseif ($port->isMetaport()) {
                ;
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

    public function comparePortsWithRepology(): bool
    {
        $this->handle->beginTransaction();

        $dictionary = new Dictionary(Config::getDbHandle());
        $overlay = Config::getOverlay();

        Logger::info('Comparing with Repology Data ...');

        $csvdata = file('data/repology.csv');

        if ($csvdata === false) {
            Logger::error('Repology CSV dump not found');
            return false;
        }

        foreach($csvdata as $line){
            list($origin, $cpe_vendor, $cpe_product, $cpe_edition, $cpe_lang, $cpe_sw_edition, $cpe_target_sw, $cpe_target_hw, $cpe_other) = explode(',', rtrim($line));
            $wfn = new WellFormedName();
            $wfn->set('part', 'a');
            $wfn->set('vendor', Product::escape($cpe_vendor));
            $wfn->set('product', Product::escape($cpe_product));

            Logger::info('Port '.$origin.' '.$wfn);

            try {
                $port = Port::loadFromDB($origin);
                if ($port === null)
                    continue;

                if ($port->isMetaport()) {
                    continue;
                }

                $product = $dictionary->findProduct($cpe_vendor, $cpe_product);

                if ($product === null) {
                    Logger::warning('Unused CPE used by Repology for port '.$port->getOrigin().' : '.$wfn);
                    $product = new Product($wfn);
                }

                if ($port->getCPEStr() == '') {
                    $nomatch = [];
                    if ($overlay->exists($port->getOrigin(), 'nomatch')) {
                        foreach ($overlay->get($port->getOrigin(), 'nomatch') as $cpe) {
                            try {
                                $nomatch[] = new Product($cpe);
                            } catch(\Exception $e) {
                                Logger::warning('Invalid nomatch CPE string for port '.$port->getOrigin().' : '.$cpe);
                            }
                        }
                    }

                    foreach ($nomatch as $prod) {
                        if ($prod->compareTo($product)) {
                            Logger::info('Ignoring false match for '.$port->getOrigin());
                            continue 2;
                        }
                    }

                    $port->addCPECandidate($product);
                    $port->setCPEStatus(Status::CHECKNEEDED);
                }
                else {
                    if ($product->isDeprecated()) {
                        Logger::warning('Repology uses deprecated CPE '.$wfn);
                    }
                    else {
                        $cpe = $port->getCPE();

                        if ($cpe !== null && $cpe->compareTo($product) === false) {
                            Logger::warning('Repology has different CPE data for port '.$port->getOrigin().' : '.$port->getCPEStr().' != '.$wfn);
                            $port->addCPECandidate($product);
                            $port->setCPEStatus(Status::CHECKNEEDED);
                        }
                    }
                }

                $port->saveToDB();
            }
            catch(\Exception $e){
               Logger::error($e->getMessage());
            }
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
        $generators[Status::CHECKNEEDED] = new MarkdownGenerator();
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

        $this->scanPorts();

        // retry scanning for the ports that failed at first attempt
        $this->scanPorts();

        if (!$this->comparePortsWithDictionary()) {
            Logger::error('Comparing ports with CPE Dictionary failed');
            return false;
        }

        if(!$this->comparePortsWithRepology()) {
            Logger::error('Comparing ports with Repology data failed');
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

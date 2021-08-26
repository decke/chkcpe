<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\CPE\Product;
use CheckCpe\CPE\Status;

class Port
{
    protected string $origin;
    protected string $portname;
    protected string $maintainer;
    protected string $cpe_str;
    protected string $cpe_vendor;
    protected string $cpe_product;

    protected string $cpe_status = Status::UNKNOWN;

    /**
     * @var array<Product>
     */
    protected array $cpe_candidates;

    public function __construct(string $origin)
    {
        $this->origin = $origin;

        $this->load();
    }

    protected function load(): bool
    {
        $portdir = Config::getPortsDir().'/'.$this->origin;

        if (!is_dir($portdir)) {
            throw new \Exception('Port directory '.$portdir.' does not exist!');
        }

        $output = [];
        $resultcode = null;

        $cmd = sprintf('%s -C %s -VPORTNAME -VMAINTAINER -VCPE_STR -VCPE_VENDOR -VCPE_PRODUCT', Config::getMakeBin(), $portdir);
        exec($cmd, $output, $resultcode);

        if ($resultcode != 0) {
            throw new \Exception('Could not extract data from port '.$this->origin);
        }

        $this->portname = $output[0];
        $this->maintainer = $output[1];
        $this->cpe_str = $output[2];
        $this->cpe_vendor = $output[3];
        $this->cpe_product = $output[4];

        return true;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getCategory(): string
    {
        if (!$this->origin || !($pos = strpos($this->origin, '/'))) {
            return '';
        }

        return substr($this->origin, 0, $pos);
    }

    public function getPortdir(): string
    {
        if (!$this->origin || !($pos = strpos($this->origin, '/'))) {
            return '';
        }

        return substr($this->origin, $pos+1);
    }

    public function getPortname(): string
    {
        return $this->portname;
    }

    public function getMaintainer(): string
    {
        return $this->maintainer;
    }

    public function getCPEStr(): string
    {
        return $this->cpe_str;
    }

    public function getCPEVendor(): string
    {
        return $this->cpe_vendor;
    }

    public function getCPEProduct(): string
    {
        return $this->cpe_product;
    }

    public function getCPEStatus(): string
    {
        return $this->cpe_status;
    }

    public function setCPEStatus(string $status): bool
    {
        $this->cpe_status = $status;
        return true;
    }

    public function addCPECandidate(Product $candidate): bool
    {
        $this->cpe_candidates[] = $candidate;
        return true;
    }

    /**
     * @return array<Product>
     */
    public function getCPECandidates(): array
    {
        return $this->cpe_candidates;
    }
}

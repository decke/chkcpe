<?php

declare(strict_types=1);

namespace CheckCpe;

use CheckCpe\CPE\Product;
use CheckCpe\CPE\Status;

class Port
{
    protected string $origin;
    protected string $portname;
    protected string $version;
    protected string $maintainer;
    protected string $cpe_str;

    protected ?Product $cpe;
    protected string $cpe_status;

    /**
     * @var array<Product>
     */
    protected array $cpe_candidates = [];

    public function __construct(string $origin, string $portname, string $version, string $maintainer, string $cpe_str, string $cpe_status = Status::UNKNOWN)
    {
        $this->origin = $origin;
        $this->portname = $portname;
        $this->version = $version;
        $this->maintainer = $maintainer;
        $this->cpe_str = $cpe_str;
        $this->cpe_status = $cpe_status;

        if ($cpe_str != '') {
            $this->cpe = new Product($cpe_str);
        }
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

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getMaintainer(): string
    {
        return $this->maintainer;
    }

    public function getCPEStr(): string
    {
        return $this->cpe_str;
    }

    public function getCPE(): ?Product
    {
        return $this->cpe;
    }

    public function setCPE(Product $cpe): bool
    {
        $this->cpe = $cpe;
        return true;
    }

    public function getCPEVendor(): string
    {
        if (is_null($this->cpe)) {
            return '';
        }

        return $this->cpe->getVendor();
    }

    public function getCPEProduct(): string
    {
        if (is_null($this->cpe)) {
            return '';
        }

        return $this->cpe->getProduct();
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

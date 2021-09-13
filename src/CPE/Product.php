<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

class Product
{
    protected string $vendor;
    protected string $product;
    protected string $version;
    protected ?Product $deprecated_by = null;

    public function __construct(string $vendor, string $product, string $version = '*')
    {
        $this->vendor = $vendor;
        $this->product = $product;
        $this->version = $version;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setDeprecatedBy(Product $product): bool
    {
        $this->deprecated_by = $product;
        return true;
    }

    public function getDeprecatedBy(): ?Product
    {
        return $this->deprecated_by;
    }

    public function isDeprecated(): bool
    {
        return !is_null($this->deprecated_by);
    }

    public function getEscapedVendor(): string
    {
        return self::escape($this->vendor);
    }

    public function getEscapedProduct(): string
    {
        return self::escape($this->product);
    }

    public function __toString(): string
    {
        return $this->vendor.':'.$this->product;
    }

    public static function escape(string $str): string
    {
        $res = preg_replace('/([^_a-zA-Z0-9])/', '\\\\$1', $str);
        if ($res === null) {
            throw new \Exception('Could not escape String');
        }

        return $res;
    }

    public static function fromString(string $cpe): Product
    {
        $cpe_parts = explode(':', $cpe);

        if (count($cpe_parts) != 2) {
            throw new \Exception('Invalid number of elements in CPE String ('.$cpe.')');
        }

        $cpe_vendor = $cpe_parts[0];
        $cpe_product = $cpe_parts[1];

        return new Product($cpe_vendor, $cpe_product);
    }

    public static function CPEtoProduct(string $cpe_fs): Product
    {
        if (substr($cpe_fs, 0, 4) != 'cpe:') {
            throw new \Exception('Invalid CPE String ('.$cpe_fs.')');
        }

        $cpe_parts = explode(':', $cpe_fs);

        if (count($cpe_parts) != 13) {
            throw new \Exception('Invalid number of elements in CPE FS String ('.$cpe_fs.')');
        }

        $cpe_std = $cpe_parts[1];
        $cpe_part = $cpe_parts[2];
        $cpe_vendor = $cpe_parts[3];
        $cpe_product = $cpe_parts[4];
        $cpe_version = $cpe_parts[5];

        if ($cpe_std != '2.3') {
            throw new \Exception('Invalid CPE Standard ('.$cpe_std.')');
        }

        return new Product($cpe_vendor, $cpe_product, $cpe_version);
    }

    public static function CPEURLtoProduct(string $cpe_url): Product
    {
        if (substr($cpe_url, 0, 4) != 'cpe:') {
            throw new \Exception('Invalid CPE URL String ('.$cpe_url.')');
        }

        $cpe_parts = explode(':', $cpe_url);

        if (count($cpe_parts) != 5) {
            throw new \Exception('Invalid number of elements in CPE URL String ('.$cpe_url.')');
        }

        $cpe_part = $cpe_parts[1];
        $cpe_vendor = $cpe_parts[2];
        $cpe_product = $cpe_parts[3];
        $cpe_version = $cpe_parts[4];

        return new Product($cpe_vendor, $cpe_product, $cpe_version);
    }
}

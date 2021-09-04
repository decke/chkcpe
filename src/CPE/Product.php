<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

class Product
{
    protected string $vendor;
    protected string $product;

    public function __construct(string $vendor, string $product)
    {
        $this->vendor = $vendor;
        $this->product = $product;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function getProduct(): string
    {
        return $this->product;
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
}

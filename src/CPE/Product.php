<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

use PacificSec\CPE\Common\WellFormedName;
use PacificSec\CPE\Naming\CPENameBinder;
use PacificSec\CPE\Naming\CPENameUnbinder;

class Product
{
    protected WellFormedName $cpe;
    protected ?Product $deprecated_by = null;

    public function __construct(string|WellFormedName $cpe)
    {
        if (is_string($cpe)) {
            $unbinder = new CPENameUnbinder();

            if (substr($cpe, 0, 5) == 'cpe:/') {
                $this->cpe = $unbinder->unbindURI($cpe);
            } else {
                $this->cpe = $unbinder->unbindFS($cpe);
            }
        } elseif ($cpe instanceof WellFormedName) {
            $this->cpe = $cpe;
        }
    }

    public function getPart(): string
    {
        return $this->cpe->get('part');
    }

    public function getVendor(): string
    {
        return $this->cpe->get('vendor');
    }

    public function getProduct(): string
    {
        return $this->cpe->get('product');
    }

    public function getVersion(): string
    {
        return $this->cpe->get('version');
    }

    public function getUpdate(): string
    {
        return $this->cpe->get('update');
    }

    public function getEdition(): string
    {
        return $this->cpe->get('edition');
    }

    public function getLanguage(): string
    {
        return $this->cpe->get('language');
    }

    public function getSwEdition(): string
    {
        return $this->cpe->get('sw_edition');
    }

    public function getTargetSW(): string
    {
        return $this->cpe->get('target_sw');
    }

    public function getTargetHW(): string
    {
        return $this->cpe->get('target_hw');
    }

    public function getOther(): string
    {
        return $this->cpe->get('other');
    }

    public function setDeprecatedBy(Product $product): bool
    {
        // recursive deprecation exists in the CPE Dictionary but it's nonsense
        if ($this->getVendor() == $product->getVendor() && $this->getProduct() == $product->getProduct()) {
            return false;
        }

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

    public function __toString(): string
    {
        return $this->getFS();
    }

    public function getFS(): string
    {
        $bind = new CPENameBinder();
        return $bind->bindToFS($this->cpe);
    }

    public function getURI(): string
    {
        $bind = new CPENameBinder();
        return $bind->bindToURI($this->cpe);
    }

    public function compareTo(Product $product): int
    {
        return strcmp($this->getVendor().':'.$this->getProduct(), $product->getVendor().':'.$product->getProduct());
    }
}

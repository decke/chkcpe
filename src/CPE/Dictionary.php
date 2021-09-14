<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

use PacificSec\CPE\Common\WellFormedName;

class Dictionary
{
    protected \PDO $handle;

    public function __construct(\PDO $handle)
    {
        $this->handle = $handle;
    }

    /**
     * @return array<Product>
     */
    public function findProductsByProductname(string $product): array
    {
        $product = Product::escape(strtolower($product));

        $stmt = $this->handle->prepare('SELECT vendor, product FROM products WHERE product = ? AND deprecatedby = ? GROUP BY vendor');
        if (!$stmt->execute([$product, ''])) {
            throw new \Exception('DB Error');
        }

        $candidates = [];

        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $wfn = new WellFormedName();
            $wfn->set('vendor', $row->vendor);
            $wfn->set('product', $row->product);

            $candidates[] = new Product($wfn);
        }

        return $candidates;
    }

    public function findProduct(string $vendor, string $product): ?Product
    {
        $wfn = new WellFormedName();
        $wfn->set('vendor', $vendor);
        $wfn->set('product', $product);

        $product = new Product($wfn);

        $stmt = $this->handle->prepare('SELECT vendor, product, deprecatedby FROM products WHERE vendor = ? AND product = ? GROUP BY vendor, product');
        if (!$stmt->execute([$product->getEscapedVendor(), $product->getEscapedProduct()])) {
            throw new \Exception('DB Error');
        }

        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        if ($row === false) {
            return null;
        }

        if (strlen($row->deprecatedby) > 0) {
            $product->setDeprecatedBy(new Product($row->deprecatedby));
        }

        return $product;
    }

    public function addProduct(Product $product): int
    {
        $deprecatedby = '';

        if ($product->isDeprecated()) {
            $deprecatedby = (string)$product->getDeprecatedBy();
        }

        $stmt = $this->handle->prepare('INSERT INTO products (vendor, product, deprecatedby) VALUES (?, ?, ?)');
        if (!$stmt->execute([$product->getEscapedVendor(), $product->getEscapedProduct(), $deprecatedby])) {
            throw new \Exception('DB Error');
        }

        return (int)$this->handle->lastInsertId();
    }

    public function addCPEFSEntry(string $cpefs): bool
    {
        $product = new Product($cpefs);

        $stmt = $this->handle->prepare('SELECT productid FROM products WHERE vendor = ? AND product = ?');
        if (!$stmt->execute([$product->getEscapedVendor(), $product->getEscapedProduct()])) {
            throw new \Exception('DB Error');
        }

        if (($row = $stmt->fetch(\PDO::FETCH_NUM)) === false) {
            $productid = $this->addProduct($product);
        } else {
            $productid = (int)$row[0];
        }

        try {
            $version = $product->getVersion();
        } catch (\TypeError) {
            $version = '';
        }

        $stmt = $this->handle->prepare('INSERT INTO cpes (productid, version, cpefs) VALUES (?, ?, ?)');
        if (!$stmt->execute([$productid, $version, $cpefs])) {
            throw new \Exception('DB Error');
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

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
            $candidates[] = new Product($row->vendor, $row->product);
        }

        return $candidates;
    }

    public function findProduct(string $vendor, string $product): ?Product
    {
        $product = new Product($vendor, $product);

        $stmt = $this->handle->prepare('SELECT vendor, product, deprecatedby FROM products WHERE vendor = ? AND product = ? GROUP BY vendor, product');
        if (!$stmt->execute([$product->getEscapedVendor(), $product->getEscapedProduct()])) {
            throw new \Exception('DB Error');
        }

        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        if ($row === false) {
            return null;
        }

        if (strlen($row['deprecatedby']) > 0) {
            $product->setDeprecatedBy(Product::fromString($row['deprecatedby']));
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
        $product = Product::CPEtoProduct($cpefs);

        $stmt = $this->handle->prepare('SELECT productid FROM products WHERE vendor = ? AND product = ?');
        if (!$stmt->execute([$product->getEscapedVendor(), $product->getEscapedProduct()])) {
            throw new \Exception('DB Error');
        }

        if (($row = $stmt->fetch(\PDO::FETCH_NUM)) === false) {
            $productid = $this->addProduct($product);
        } else {
            $productid = (int)$row[0];
        }

        $stmt = $this->handle->prepare('INSERT INTO cpes (productid, version, cpefs) VALUES (?, ?, ?)');
        if (!$stmt->execute([$productid, $product->getVersion(), $cpefs])) {
            throw new \Exception('DB Error');
        }

        return true;
    }
}

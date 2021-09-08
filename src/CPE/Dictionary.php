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

        $stmt = $this->handle->prepare('SELECT vendor, product FROM products WHERE product = ? GROUP BY vendor');
        if (!$stmt->execute([$product])) {
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

        $stmt = $this->handle->prepare('SELECT vendor, product FROM products WHERE vendor = ? AND product = ? GROUP BY vendor, product');
        if (!$stmt->execute([$product->getEscapedVendor(), $product->getEscapedProduct()])) {
            throw new \Exception('DB Error');
        }

        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        if ($row === false) {
            return null;
        }

        return $product;
    }

    public function addProduct(string $vendor, string $product): int
    {
        $vendor = Product::escape($vendor);
        $product = Product::escape($product);

        $stmt = $this->handle->prepare('INSERT INTO products (vendor, product) VALUES (?, ?)');
        if (!$stmt->execute([$vendor, $product])) {
            throw new \Exception('DB Error');
        }

        return (int)$this->handle->lastInsertId();
    }

    public function addCPEFSEntry(string $vendor, string $product, string $cpefs): bool
    {
        $stmt = $this->handle->prepare('SELECT productid FROM products WHERE vendor = ? AND product = ?');
        if (!$stmt->execute([Product::escape($vendor), Product::escape($product)])) {
            throw new \Exception('DB Error');
        }

        if (($row = $stmt->fetch(\PDO::FETCH_NUM)) === false) {
            $productid = $this->addProduct($vendor, $product);
        } else {
            $productid = (int)$row[0];
        }

        $stmt = $this->handle->prepare('INSERT INTO cpes (productid, cpefs) VALUES (?, ?)');
        if (!$stmt->execute([$productid, $cpefs])) {
            throw new \Exception('DB Error');
        }

        return true;
    }
}

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
}

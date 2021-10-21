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
        $product = strtolower($product);

        $stmt = $this->handle->prepare('SELECT vendor, product FROM products WHERE product LIKE ? AND deprecatedby = ? GROUP BY vendor');
        if (!$stmt->execute(['%'.$product.'%', ''])) {
            throw new \Exception('DB Error');
        }

        $candidates = [];

        while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
            $wfn = new WellFormedName();
            $wfn->set('part', 'a');
            $wfn->set('vendor', $row->vendor);
            $wfn->set('product', $row->product);

            $candidates[] = new Product($wfn);
        }

        return $candidates;
    }

    public function findProduct(string $vendor, string $product): ?Product
    {
        $wfn = new WellFormedName();
        $wfn->set('part', 'a');
        $wfn->set('vendor', strtolower($vendor));
        $wfn->set('product', strtolower($product));

        $prd = new Product($wfn);

        $stmt = $this->handle->prepare('SELECT vendor, product, deprecatedby FROM products WHERE vendor = ? AND product = ?');
        if (!$stmt->execute([$prd->getVendor(), $prd->getProduct()])) {
            throw new \Exception('DB Error');
        }

        $row = $stmt->fetch(\PDO::FETCH_NUM);
        if ($row === false) {
            return null;
        }

        if (strlen($row[2]) > 0) {
            $prd->setDeprecatedBy(new Product((string)$row[2]));
        }

        return $prd;
    }

    public function addProduct(Product $prd): bool
    {
        $deprecatedby = '';

        if ($prd->isDeprecated()) {
            $deprecatedby = (string)$prd->getDeprecatedBy();
        }

        try {
            $vendor = $prd->getVendor();
            $product = $prd->getProduct();
        } catch (\TypeError) {
            return false;
        }

        $stmt = $this->handle->prepare('SELECT productid FROM products WHERE vendor = ? AND product = ?');
        if (!$stmt->execute([$vendor, $product])) {
            throw new \Exception('DB Error');
        }

        if (($row = $stmt->fetch(\PDO::FETCH_NUM)) === false) {
            $stmt = $this->handle->prepare('INSERT INTO products (vendor, product, deprecatedby) VALUES (?, ?, ?)');
            if (!$stmt->execute([$vendor, $product, $deprecatedby])) {
                throw new \Exception('DB Error');
            }

            $productid = (int)$this->handle->lastInsertId();
        } else {
            $productid = (int)$row[0];
        }

        $stmt = $this->handle->prepare('INSERT INTO cpes (productid, cpefs) VALUES (?, ?)');
        if (!$stmt->execute([$productid, (string)$prd])) {
            throw new \Exception('DB Error');
        }

        return true;
    }
}

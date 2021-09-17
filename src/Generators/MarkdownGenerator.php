<?php

declare(strict_types=1);

namespace CheckCpe\Generators;

use CheckCpe\Port;
use CheckCpe\CPE\Status;

class MarkdownGenerator extends Generator
{
    protected function getHeader(): string
    {
        $header = '';
        $header .= sprintf("Date: %s\n", date(DATE_RFC850));
        $header .= sprintf("\n");
        $header .= sprintf("| Port | Maintainer | Status | Comment |\n");
        $header .= sprintf("|--|--|--|--|\n");

        return $header;
    }

    protected function getFooter(): string
    {
        return '';
    }

    protected function render(Port $port): string
    {
        return sprintf(
            "| [%s](https://freshports.org/%s) | %s | ![%s](https://img.shields.io/badge/%s-%s) | %s |\n",
            $port->getOrigin(),
            $port->getOrigin(),
            $port->getMaintainer(),
            $port->getCPEStatus(),
            $port->getCPEStatus(),
            $port->getColor(),
            $this->genMessage($port)
        );
    }

    protected function genMessage(Port $port): string
    {
        switch ($port->getCPEStatus()) {
             case Status::VALID:
                 return 'found CPE';

             case Status::INVALID:
                 return sprintf('Vendor %s Product %s not found in DB', $port->getCPEVendor(), $port->getCPEProduct());

             case Status::DEPRECATED:
                 $cpe = $port->getCPE();
                 if ($cpe === null) {
                     return '';
                 }

                 $deprecatedby = $cpe->getDeprecatedBy();
                 if ($deprecatedby === null) {
                     return '';
                 }

                 return sprintf('Deprecated by Vendor %s Product %s', $deprecatedby->getVendor(), $deprecatedby->getProduct());

             case Status::CHECKNEEDED:
             case Status::READYTOCOMMIT:
                 $msg = '';

                 foreach ($port->getCPECandidates() as $product) {
                     $msg .= $product.' ';
                 }

                 return rtrim($msg);

             default:
                 return '';
         }
    }
}

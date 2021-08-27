<?php

declare(strict_types=1);

namespace CheckCpe\Generators;

use CheckCpe\Port;

class WeightedMarkdownGenerator extends MarkdownGenerator
{
    /**
     * @var array<string, int>
     */
    protected array $priority = [];

    /**
     * @param string $title
     * @param array<string, int> $priority
     */
    public function __construct(string $title = '', array $priority = [])
    {
        $this->title = $title;
        $this->priority = $priority;
    }

    public function addPort(Port $port): bool
    {
        $origin = $port->getOrigin();
        $key = '00000-'.$origin;

        if (isset($this->priority[$origin])) {
            $key = sprintf('%05d-%s', $this->priority[$origin], $origin);
        }

        if (isset($this->ports[$key])) {
            return false;
        }

        $this->ports[$key] = $port;
        return true;
    }
}

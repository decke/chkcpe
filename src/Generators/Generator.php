<?php

declare(strict_types=1);

namespace CheckCpe\Generators;

use CheckCpe\Port;

abstract class Generator
{
    protected string $title;

    /**
     * @var array<string,Port>
     */
    protected array $ports = [];

    public function __construct(string $title = '')
    {
        $this->title = $title;
    }

    public function count(): int
    {
        return count($this->ports);
    }

    public function addPort(Port $port): bool
    {
        if (isset($this->ports[$port->getOrigin()])) {
            return false;
        }

        $this->ports[$port->getOrigin()] = $port;
        return true;
    }

    public function toFile(string $file): bool
    {
        ksort($this->ports);

        $content = $this->getHeader();

        $limit = 2000;
        $count = 0;
        foreach ($this->ports as $port) {
            $content .= $this->render($port);

            if (++$count > $limit) {
                break;
            }
        }

        $content .= $this->getFooter();

        if ($count >= $limit) {
            $content .= sprintf("\n %d more ports", count($this->ports) - $limit);
        }

        if (file_put_contents($file, $content) === false) {
            return false;
        }

        return true;
    }

    abstract protected function render(Port $port): string;

    abstract protected function getHeader(): string;

    abstract protected function getFooter(): string;
}

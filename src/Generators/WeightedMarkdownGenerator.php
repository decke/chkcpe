<?php

declare(strict_types=1);

namespace CheckCpe\Generators;

use CheckCpe\Config;
use CheckCpe\Overlay;
use CheckCpe\Port;

class WeightedMarkdownGenerator extends MarkdownGenerator
{
    private Overlay $overlay;

    /**
     * @param string $title
     */
    public function __construct(string $title = '')
    {
        $this->title = $title;
        $this->overlay = Config::getOverlay();
    }

    public function addPort(Port $port): bool
    {
        $origin = $port->getOrigin();
        $key = '9999-'.$origin;

        if ($this->overlay->exists($origin, 'priority')) {
            $priority = (int)$this->overlay->exists($origin, 'priority');
            $key = sprintf('%04d-%s', 9999-$priority, $origin);
        }

        if (isset($this->ports[$key])) {
            return false;
        }

        $this->ports[$key] = $port;
        return true;
    }
}

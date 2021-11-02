<?php

declare(strict_types=1);

namespace CheckCpe;

class Overlay
{
    protected string $filename;

    /**
     * @var array<string,mixed>
     */
    protected array $data;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->loadFromFile();
    }

    public function loadFromFile(): bool
    {
        if (!file_exists($this->filename)) {
            throw new \Exception('Overlay file '.$this->filename.' not found!');
        }

        $content = file_get_contents($this->filename);
        if ($content === false) {
            throw new \Exception('COuld not read overlayfile '.$this->filename);
        }

        $this->data = json_decode($content, true);
        return true;
    }

    public function saveToFile(): bool
    {
        ksort($this->data);

        $content = json_encode($this->data, JSON_PRETTY_PRINT);

        if (file_put_contents($this->filename, $content) === false) {
            return false;
        }

        return true;
    }

    public function exists(string $origin, string $key): bool
    {
        return isset($this->data[$origin][$key]);
    }

    public function get(string $origin, string $key = ''): mixed
    {
        if ($key == '') {
            if (isset($this->data[$origin])) {
                return $this->data[$origin];
            }

            return false;
        }

        if ($this->exists($origin, $key)) {
            return $this->data[$origin][$key];
        }

        return false;
    }

    public function set(string $origin, string $key, mixed $value): mixed
    {
        $this->data[$origin][$key] = $value;
        return true;
    }

    public function unset(string $origin, string $key = ''): bool
    {
        if ($key == '') {
            if (isset($this->data[$origin])) {
                unset($this->data[$origin]);
            }

            return true;
        }

        if ($this->exists($origin, $key)) {
            unset($this->data[$origin][$key]);
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function listPorts(): array
    {
        $ports = [];

        foreach ($this->data as $origin => $data) {
            $ports[] = $origin;
        }

        return $ports;
    }
}

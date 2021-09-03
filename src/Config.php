<?php

declare(strict_types=1);

namespace CheckCpe;

/**
 * Configuration class to store various static settings.
 */
class Config
{
    protected static string $portsdir = '/usr/ports';
    protected static string $logsdir = 'logs';
    protected static string $datadir = 'data';
    protected static string $makebin = '/usr/bin/make';
    protected static string $datasource = 'sqlite:data/cpe.sqlite';
    protected static ?\PDO $handle = null;

    public static function getPortsDir(): string
    {
        $ports = getenv('PORTSDIR');
        if ($ports !== false) {
            self::$portsdir = $ports;
        }

        $ports = realpath(self::$portsdir);
        if ($ports !== false) {
            self::$portsdir = $ports;
        }

        return self::$portsdir;
    }

    public static function getLogsDir(): string
    {
        $logs = getenv('LOGSDIR');
        if ($logs !== false) {
            self::$logsdir = $logs;
        }

        return self::$logsdir;
    }

    public static function getDataDir(): string
    {
        $data = getenv('DATADIR');
        if ($data !== false) {
            self::$datadir = $data;
        }

        return self::$datadir;
    }

    public static function getMakeBin(): string
    {
        $make = getenv('MAKE');
        if ($make !== false) {
            self::$makebin = $make;
        }

        return self::$makebin;
    }

    public static function getDataSource(): string
    {
        $ds = getenv('CPEDB');
        if ($ds !== false) {
            self::$datasource = $ds;
        }

        return self::$datasource;
    }

    public static function getDbHandle(): \PDO
    {
        if (self::$handle === null) {
            self::$handle = new \PDO(self::getDataSource());
            self::$handle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$handle->exec('PRAGMA foreign_keys = ON;');
            self::$handle->exec('PRAGMA encoding = "UTF-8";');
        }

        return self::$handle;
    }

    /**
     * @return array<string, int>
     */
    public static function getPriorityData(): array
    {
        $file = Config::getDataDir().'/portpriority.json';

        if (!file_exists($file)) {
            throw new \Exception('Data file '.$file.' not found!');
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \Exception('Could not read '.$file);
        }

        return json_decode($content, true);
    }

    /**
     * @return array<string,string>
     */
    public static function getAddMatchData(): array
    {
        $file = Config::getDataDir().'/cpeaddmatch.json';

        if (!file_exists($file)) {
            throw new \Exception('Data file '.$file.' not found!');
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \Exception('Could not read '.$file);
        }

        return json_decode($content, true);
    }

    /**
     * @return array<string,array<string>>
     */
    public static function getFalseMatchData(): array
    {
        $file = Config::getDataDir().'/cpefalsematch.json';

        if (!file_exists($file)) {
            throw new \Exception('Data file '.$file.' not found!');
        }

        $content = file_get_contents($file);
        if ($content === false) {
            throw new \Exception('Could not read '.$file);
        }

        return json_decode($content, true);
    }
}

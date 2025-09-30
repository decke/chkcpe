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
    protected static string $datasource = 'sqlite:data/chkcpe.db';
    protected static string $cpedictionary = 'data/nvdcpe-2.0-chunks/*.json';
    protected static string $overlayfile = 'data/overlay.json';
    protected static ?\PDO $handle = null;
    protected static ?Overlay $overlay = null;

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
        $ds = getenv('CHKCPEDB');
        if ($ds !== false) {
            self::$datasource = $ds;
        }

        return self::$datasource;
    }

    public static function getCPEDictionary(): string
    {
        $cpedictionary = getenv('CPEDICTIONARY');
        if ($cpedictionary !== false) {
            self::$cpedictionary = $cpedictionary;
        }

        return self::$cpedictionary;
    }

    public static function getOverlayFile(): string
    {
        $overlayfile = getenv('OVERLAYFILE');
        if ($overlayfile !== false) {
            self::$overlayfile = $overlayfile;
        }

        return self::$overlayfile;
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

    public static function getOverlay(): Overlay
    {
        if (self::$overlay === null) {
            self::$overlay = new Overlay(self::getOverlayFile());
        }

        return self::$overlay;
    }
}

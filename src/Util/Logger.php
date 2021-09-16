<?php

declare(strict_types=1);

namespace CheckCpe\Util;

class Logger
{
    protected static float $startTime;

    public static function start(): void
    {
        self::$startTime = microtime(true);
    }

    public static function debug(string $msg): void
    {
        self::log('debug', $msg);
    }

    public static function info(string $msg): void
    {
        self::log('info', $msg);
    }

    public static function warning(string $msg): void
    {
        self::log('warning', $msg);
    }

    public static function error(string $msg): void
    {
        self::log('error', $msg);
    }

    protected static function log(string $level, string $msg): void
    {
        printf("[%s] [%s] %s\n", self::getRuntime(), ucfirst($level), $msg);
    }

    protected static function getRuntime(): string
    {
        if (self::$startTime == 0) {
            return '';
        }

        $time = ((microtime(true) - self::$startTime) * 1000);

        return sprintf('%02d:%05.02f', floor($time/60000), ($time % 60000)/1000);
    }
}

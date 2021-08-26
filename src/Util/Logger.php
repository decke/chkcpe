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
        printf("[%s][%s] %s\n", self::getRuntime(), $level, $msg);
    }

    protected static function getRuntime(): string
    {
        if (self::$startTime == 0) {
            return '';
        }

        $time = ((microtime(true) - self::$startTime) * 1000);

        if ($time > 60000) {
            $mins = floor($time / 60000);
            $secs = round((($time % 60000) / 1000), 2);
            $time = $mins.' mins';

            if ($secs !== 0) {
                $time .= ', '.$secs.' secs';
            }
        } elseif ($time > 1000) {
            $time = round(($time / 1000), 2).' secs';
        } else {
            $time = round($time).' ms';
        }

        return $time;
    }
}

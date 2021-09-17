<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

class Status
{
    // temporary
    public const NEW = 'new';
    public const SCANNED = 'scanned';

    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const DEPRECATED = 'deprecated';
    public const CHECKNEEDED = 'checkneeded';
    public const READYTOCOMMIT = 'readytocommit';
    public const UNKNOWN = 'unknown';

    public static function getColor(string $status): string
    {
        $colors = [
            self::VALID => 'brightgreen',
            self::INVALID => 'red',
            self::DEPRECATED => 'red',
            self::CHECKNEEDED => 'orange',
            self::READYTOCOMMIT => 'orange',
            self::UNKNOWN => 'grey'
        ];

        if (isset($colors[$status])) {
            return $colors[$status];
        }

        return 'black';
    }
}

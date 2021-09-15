<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

class Status
{
    public const NEW = 'new';
    public const SCANNED = 'scanned';
    public const VALID = 'valid';
    public const DEPRECATED = 'deprecated';
    public const INVALID = 'invalid';
    public const CHECKNEEDED = 'checkneeded';
    public const UNKNOWN = 'unknown';
}

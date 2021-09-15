<?php

declare(strict_types=1);

namespace CheckCpe\CPE;

class Status
{
    // temporary
    public const NEW = 'new';
    public const SCANNED = 'scanned';
    public const INCONSISTENT = 'inconsistent';

    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const DEPRECATED = 'deprecated';
    public const CHECKNEEDED = 'checkneeded';
    public const READYTOCOMMIT = 'readytocommit';
    public const UNKNOWN = 'unknown';
}

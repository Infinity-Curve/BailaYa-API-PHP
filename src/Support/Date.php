<?php
declare(strict_types=1);

namespace BailaYa\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Date
{
    /** Parse 'YYYY-MM-DD' to UTC midnight (no local shift). */
    public static function parseDateOnlyUTC(string $yyyyMmDd): DateTimeImmutable
    {
        return new DateTimeImmutable($yyyyMmDd . ' 00:00:00', new DateTimeZone('UTC'));
    }

    /** Accept 'YYYY-MM-DD' (UTC midnight) or ISO datetime. */
    public static function parseApiDateUTC(string $value): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return self::parseDateOnlyUTC($value);
        }
        return new DateTimeImmutable($value);
    }

    /** Format to YYYY-MM-DD using UTC calendar. */
    public static function formatUTCDate(DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
}

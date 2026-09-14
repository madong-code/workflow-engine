<?php

namespace madong\workflow\libs\utils;

class DateTimeHelper
{
    public static function now(): int
    {
        return time();
    }

    public static function format(int $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, $timestamp);
    }

    public static function parse(string $datetime): int
    {
        return strtotime($datetime) ?: 0;
    }
}

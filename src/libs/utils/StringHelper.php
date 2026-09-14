<?php

namespace madong\workflow\libs\utils;

class Str
{
    public static function isBlank(?string $str): bool
    {
        return $str === null || trim($str) === '';
    }

    public static function isNotBlank(?string $str): bool
    {
        return !self::isBlank($str);
    }

    public static function defaultIfBlank(?string $str, string $default): string
    {
        return self::isBlank($str) ? $default : $str;
    }
}

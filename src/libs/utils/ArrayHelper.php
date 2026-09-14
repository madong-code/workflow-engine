<?php

namespace madong\workflow\libs\utils;

class ArrayHelper
{
    public static function get(array $arr, string $key, mixed $default = null): mixed
    {
        return $arr[$key] ?? $default;
    }

    public static function has(array $arr, string $key): bool
    {
        return array_key_exists($key, $arr);
    }

    public static function flatten(array $arr): array
    {
        $result = [];
        array_walk_recursive($arr, fn($v) => $result[] = $v);
        return $result;
    }
}

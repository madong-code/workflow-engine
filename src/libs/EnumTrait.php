<?php

namespace madong\workflow\libs;

trait EnumTrait
{
    public static function all(): array
    {
        return self::cases();
    }

    public static function codeOf(mixed $code): ?static
    {
        if ($code instanceof static) return $code;
        return self::tryFrom($code);
    }

    public static function getName(mixed $code): string
    {
        $case = self::codeOf($code);
        if ($case === null) return '';
        if (method_exists($case, 'label')) return $case->label();
        return $case->name;
    }

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}

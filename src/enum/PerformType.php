<?php

namespace madong\workflow\enum;

enum PerformType: int
{
    case NORMAL      = 0;  // 普通
    case COUNTERSIGN = 1;  // 会签

    public function label(): string
    {
        return match ($this) {
            self::NORMAL      => '普通',
            self::COUNTERSIGN => '会签',
        };
    }

    public static function fromCode(mixed $code): ?self
    {
        if ($code === null || $code === '') return null;
        if ($code instanceof self) return $code;
        if (is_string($code)) {
            return match (strtoupper($code)) {
                'ANY', 'NORMAL' => self::NORMAL,
                'ALL', 'COUNTERSIGN' => self::COUNTERSIGN,
                default => self::tryFrom((int) $code),
            };
        }
        return self::tryFrom((int) $code);
    }
}

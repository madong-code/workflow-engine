<?php

namespace madong\workflow\enum;

enum CountersignType: int
{
    case PARALLEL   = 0;  // 并行会签
    case SEQUENTIAL = 1;  // 串行会签

    public function label(): string
    {
        return match ($this) {
            self::PARALLEL   => '并行会签',
            self::SEQUENTIAL => '串行会签',
        };
    }

    public static function fromCode(mixed $code): ?self
    {
        if ($code === null || $code === '') return null;
        if ($code instanceof self) return $code;
        if (is_string($code)) {
            return match (strtoupper($code)) {
                'PARALLEL' => self::PARALLEL,
                'SEQUENTIAL' => self::SEQUENTIAL,
                default => self::tryFrom((int) $code),
            };
        }
        return self::tryFrom((int) $code);
    }
}

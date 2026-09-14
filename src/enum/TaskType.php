<?php

namespace madong\workflow\enum;

enum TaskType: int
{
    case MAJOR  = 0;  // 主办
    case MINOR  = 1;  // 协办
    case CC     = 2;  // 抄送

    public function label(): string
    {
        return match ($this) {
            self::MAJOR => '主办',
            self::MINOR => '协办',
            self::CC    => '抄送',
        };
    }

    public static function fromCode(mixed $code): ?self
    {
        if ($code === null || $code === '') return null;
        if ($code instanceof self) return $code;
        return self::tryFrom((int) $code);
    }
}

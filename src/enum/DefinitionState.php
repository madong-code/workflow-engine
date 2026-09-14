<?php

namespace madong\workflow\enum;

enum DefinitionState: int
{
    case DRAFT     = 0;  // 草稿
    case PUBLISHED = 1;  // 已发布
    case DISABLED  = 2;  // 已禁用

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => '草稿',
            self::PUBLISHED => '已发布',
            self::DISABLED  => '已禁用',
        };
    }
}

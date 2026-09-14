<?php

namespace madong\workflow\enum;

enum TaskState: int
{
    case DOING      = 0;  // 执行中
    case FINISHED   = 1;  // 已完成
    case CANCELED   = 2;  // 已取消
    case RESERVED   = 3;  // 已保留
    case SUSPENDED  = 4;  // 协办中（前加签/运行时会签挂起，等待协办组办结）

    public function label(): string
    {
        return match ($this) {
            self::DOING      => '执行中',
            self::FINISHED   => '已完成',
            self::CANCELED   => '已取消',
            self::RESERVED   => '已保留',
            self::SUSPENDED  => '协办中',
        };
    }
}

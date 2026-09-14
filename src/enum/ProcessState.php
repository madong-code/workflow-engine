<?php

namespace madong\workflow\enum;

enum ProcessState: int
{
    case ACTIVE    = 0;  // 活动中
    case FINISHED  = 1;  // 已完成
    case REJECTED  = 2;  // 已驳回
    case SUSPENDED = 3;  // 已挂起
    case WITHDRAWN = 4;  // 已撤回（申请人取回）
    case CANCELED  = 5;  // 已撤销（系统/管理员终止）

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE    => '活动中',
            self::FINISHED  => '已完成',
            self::REJECTED  => '已驳回',
            self::SUSPENDED => '已挂起',
            self::WITHDRAWN => '已撤回',
            self::CANCELED  => '已撤销',
        };
    }
}

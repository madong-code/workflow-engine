<?php

namespace madong\workflow\enum;

enum SubmitType: string
{
    case AGREE  = 'agree';   // 同意
    case REJECT = 'reject';  // 拒绝

    public function label(): string
    {
        return match ($this) {
            self::AGREE  => '同意',
            self::REJECT => '拒绝',
        };
    }
}

<?php

namespace madong\workflow\libs\utils;

use madong\workflow\exception\FlowException;

class AssertHelper
{
    public static function notNull(mixed $value, string $message = '值不能为空'): void
    {
        if ($value === null) {
            throw new FlowException($message);
        }
    }

    public static function notEmpty(mixed $value, string $message = '值不能为空'): void
    {
        if (empty($value)) {
            throw new FlowException($message);
        }
    }

    public static function isTrue(bool $condition, string $message = '条件不满足'): void
    {
        if (!$condition) {
            throw new FlowException($message);
        }
    }
}

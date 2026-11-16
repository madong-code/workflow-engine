<?php
/**
 * workflow demo · 演示用 ID 生成器
 *
 * 纯 PHP 时间戳雪花式长整 ID（毫秒 << 12 | 序列），不依赖任何框架；
 * 输出为字符串以避免 JS 端 bigint 精度丢失。
 */

declare(strict_types=1);

namespace madong\workflow\demo\model;

final class Id
{
    private static int $seq = 0;

    public static function next(): string
    {
        self::$seq = (self::$seq + 1) % 4096;
        $ms = (int) (microtime(true) * 1000);
        return (string) (($ms << 12) | self::$seq);
    }
}

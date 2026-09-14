<?php

namespace madong\workflow\aop;

class AspectTrace
{
    /** @var array<string, array> */
    private static array $traces = [];
    private static ?string $currentProcessId = null;

    public static function setCurrentProcessId(?string $pid): void
    {
        self::$currentProcessId = $pid;
    }

    public static function record(string $name, JoinPoint $jp, float $ms, string $status = 'OK'): void
    {
        $pid = self::$currentProcessId;
        if ($pid === null) return;
        self::$traces[$pid][] = [
            'aspect'    => $name,
            'joinPoint' => $jp->value,
            'duration'  => round($ms, 3),
            'status'    => $status,
            'ts'        => microtime(true),
            'mem'       => memory_get_usage(true),
        ];
    }

    public static function getTrace(string $pid): array
    {
        return self::$traces[$pid] ?? [];
    }

    public static function format(string $pid): string
    {
        $records = self::getTrace($pid);
        if (empty($records)) return '(无追踪)';
        $lines = [];
        foreach ($records as $i => $r) {
            $lines[] = sprintf('[%d] %-10s %-30s %7.3fms %s',
                $i, $r['status'], $r['aspect'], $r['duration'], $r['joinPoint']);
        }
        return implode("\n", $lines);
    }

    public static function clear(string $pid): void
    {
        unset(self::$traces[$pid]);
    }

    public static function clearAll(): void
    {
        self::$traces = [];
    }
}

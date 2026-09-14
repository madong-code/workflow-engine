<?php

namespace madong\workflow\libs\utils;

class Logger
{
    private static string $logDir = '';

    public static function setLogDir(string $dir): void
    {
        self::$logDir = $dir;
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        $line = sprintf('[%s] [%s] %s %s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        if (!empty(self::$logDir) && is_dir(self::$logDir)) {
            $file = self::$logDir . '/' . date('Y-m-d') . '.log';
            file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
        } else {
            error_log($line);
        }
    }
}

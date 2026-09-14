<?php

namespace madong\workflow\libs\utils;

class ProcessFlowUtils
{
    /**
     * 合并变量 (overlay 覆盖 base)
     */
    public static function mergeVariables(array $base, array $overlay): array
    {
        return array_merge($base, $overlay);
    }

    /**
     * 替换字符串中的变量 ${var}
     */
    public static function replaceVariables(string $template, array $variables): string
    {
        return preg_replace_callback('/\$\{(\w+)\}/', function ($matches) use ($variables) {
            return (string) ($variables[$matches[1]] ?? $matches[0]);
        }, $template);
    }
}

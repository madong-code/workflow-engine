<?php

namespace madong\workflow\expression;

use madong\workflow\context\ProcessVariables;

class DefaultExpressionEngine implements ExpressionEngine
{
    public function evaluate(string $expr, ProcessVariables $variables): mixed
    {
        // 替换 ${var} 格式的变量引用
        $resolved = $this->resolveVariables($expr, $variables);

        // 简单表达式求值
        if (preg_match('/^[\d\s\+\-\*\/\(\)\<\>\=\!\&\|\.]+$/', $resolved)) {
            try {
                $result = eval("return {$resolved};");
                return $result;
            } catch (\Throwable) {
                return $resolved;
            }
        }

        return $resolved;
    }

    public function evaluateToBool(string $expr, ProcessVariables $variables): bool
    {
        $result = $this->evaluate($expr, $variables);
        if (is_bool($result)) return $result;
        if (is_string($result)) {
            $lower = strtolower($result);
            if ($lower === 'true' || $lower === '1') return true;
            if ($lower === 'false' || $lower === '0' || $lower === '') return false;
        }
        return (bool) $result;
    }

    private function resolveVariables(string $expr, ProcessVariables $variables): string
    {
        $callback = function ($matches) use ($variables) {
            $value = $variables->get($matches[1]);
            if ($value === null) return 'null';
            if (is_bool($value)) return $value ? 'true' : 'false';
            if (is_numeric($value)) return (string) $value;
            if (is_string($value)) return "'{$value}'";
            return json_encode($value);
        };

        // 替换 ${var} 格式变量引用
        $expr = preg_replace_callback('/\$\{(\w+)\}/', $callback, $expr);
        // 替换 #var 前缀格式变量引用（LogicFlow 设计器导出格式）
        $expr = preg_replace_callback('/#([\w]+)/', $callback, $expr);

        return $expr;
    }
}

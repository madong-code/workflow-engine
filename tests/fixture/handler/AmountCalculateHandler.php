<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * 自定义节点测试处理器（对应 wf-custom-node.json 的 clazz 配置）
 *
 * json clazz 用点号：madong.workflow.tests.fixture.handler.AmountCalculateHandler
 * 根据 args（如 "a,b"）从流程变量读取操作数并计算求和，返回结果供 custom 节点写入 var。
 */
class AmountCalculateHandler
{
    /**
     * @param ExecutionContext $ctx 执行上下文
     * @param mixed $args 节点配置的 args（如 "a,b"）
     */
    public function calculate(ExecutionContext $ctx, mixed $args): mixed
    {
        $keys = is_string($args) ? array_map('trim', explode(',', $args)) : [];
        $sum = 0;
        foreach ($keys as $key) {
            if ($key === '') continue;
            $value = $ctx->getVariable($key);
            if (is_numeric($value)) {
                $sum += (float) $value;
            }
        }
        return $sum;
    }
}

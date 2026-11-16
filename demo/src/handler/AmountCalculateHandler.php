<?php
/**
 * workflow demo · 自定义节点处理器（扩展点演示）
 *
 * 流程定义 json 中以点号类名引用（json 不允许 \）：
 *   "clazz": "madong.workflow.demo.handler.AmountCalculateHandler"
 * 引擎 CustomNode 会实例化并调用 handle($ctx, $args)，结果写入 var 指定的流程变量。
 */

declare(strict_types=1);

namespace madong\workflow\demo\handler;

use madong\workflow\context\ExecutionContext;

class AmountCalculateHandler
{
    /**
     * 金额计算演示：读取表单金额 f_amount，计算大写币值标记与含税金额写回变量
     */
    public function handle(ExecutionContext $ctx, mixed $args = null): mixed
    {
        $amount = (float) ($ctx->getVariable('f_amount', $args ?? 0));
        $taxRate = 0.06;

        return [
            'amount'       => $amount,
            'tax_included' => round($amount * (1 + $taxRate), 2),
            'tax_rate'     => $taxRate,
            'level'        => $amount >= 10000 ? 'high' : 'normal',
        ];
    }
}

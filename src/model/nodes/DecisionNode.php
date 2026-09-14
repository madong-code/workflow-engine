<?php

namespace madong\workflow\model\nodes;

use madong\workflow\exception\NodeException;
use madong\workflow\model\NodeModel;
use madong\workflow\context\ExecutionContext;

class DecisionNode extends NodeModel
{
    public function __construct(
        string  $name,
        string  $displayName = '',
        \madong\workflow\model\vo\Layout $layout = new \madong\workflow\model\vo\Layout(0, 0, 0, 0),
        array   $inputs = [],
        array   $outputs = [],
        ?string $preInterceptors = null,
        ?string $postInterceptors = null,
        public readonly ?string $expr = null,
        public readonly ?string $handleClass = null,
    ) {
        parent::__construct($name, $displayName, $layout, $inputs, $outputs, $preInterceptors, $postInterceptors);
    }

    public function execute(ExecutionContext $ctx): void
    {
        // 决策节点: 评估每条输出边的表达式，执行匹配的边
        $matched = false;
        foreach ($this->outputs as $transition) {
            if ($transition->evaluateEnabled($ctx)) {
                $transition->execute($ctx);
                $matched = true;
                break;  // 决策节点只走一条分支
            }
        }

        // 没有匹配的边，走默认（无表达式的边）；无默认边则明确报错，避免实例静默卡死
        if (!$matched) {
            foreach ($this->outputs as $transition) {
                if ($transition->expr === null || $transition->expr === '') {
                    $transition->execute($ctx);
                    return;
                }
            }
            throw new NodeException(
                "决策节点 [{$this->displayName}({$this->name})] 所有分支条件均不满足且未配置默认分支，流程无法继续"
            );
        }
    }
}

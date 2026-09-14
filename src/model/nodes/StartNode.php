<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\model\vo\Layout;
use madong\workflow\context\ExecutionContext;

class StartNode extends NodeModel
{
    public function execute(ExecutionContext $ctx): void
    {
        // 开始节点: 直接执行所有输出边
        foreach ($this->outputs as $transition) {
            if ($transition->evaluateEnabled($ctx)) {
                $transition->execute($ctx);
            }
        }
    }
}

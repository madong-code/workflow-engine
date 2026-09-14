<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\context\ExecutionContext;

class ForkNode extends NodeModel
{
    public function execute(ExecutionContext $ctx): void
    {
        // 分支节点: 执行所有输出边 (并行)
        foreach ($this->outputs as $transition) {
            if ($transition->evaluateEnabled($ctx)) {
                $transition->execute($ctx);
            }
        }
    }
}

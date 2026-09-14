<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\context\ExecutionContext;

class JoinNode extends NodeModel
{
    public function execute(ExecutionContext $ctx): void
    {
        // 合并节点: 检查是否所有分支都已完成
        $handler = new \madong\workflow\handler\MergeBranchHandler($this);
        $handler->handle($ctx);

        // 如果合并完成，继续执行输出边
        if ($ctx->result->isMerged()) {
            foreach ($this->outputs as $transition) {
                if ($transition->evaluateEnabled($ctx)) {
                    $transition->execute($ctx);
                }
            }
        }
    }
}

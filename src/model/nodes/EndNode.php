<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\context\ExecutionContext;

class EndNode extends NodeModel
{
    public function execute(ExecutionContext $ctx): void
    {
        // 结束节点: 由 EndProcessHandler 处理
        $handler = new \madong\workflow\handler\EndProcessHandler();
        $handler->handle($ctx);
    }
}

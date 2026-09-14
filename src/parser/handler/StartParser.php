<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\StartNode;

class StartParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        // 开始节点无额外属性
    }

    public function build(): StartNode
    {
        return $this->builder->buildStartNode();
    }
}

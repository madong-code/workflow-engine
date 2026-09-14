<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\EndNode;

class EndParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        // 结束节点无额外属性
    }

    public function build(): EndNode
    {
        return $this->builder->buildEndNode();
    }
}

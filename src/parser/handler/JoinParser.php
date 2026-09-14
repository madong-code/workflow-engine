<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\JoinNode;

class JoinParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        // 合并节点无额外属性
    }

    public function build(): JoinNode
    {
        return $this->builder->buildJoinNode();
    }
}

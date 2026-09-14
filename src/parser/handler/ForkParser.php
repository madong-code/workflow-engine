<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\ForkNode;

class ForkParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        // 分支节点无额外属性
    }

    public function build(): ForkNode
    {
        return $this->builder->buildForkNode();
    }
}

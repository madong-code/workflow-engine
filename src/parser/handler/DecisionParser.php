<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\DecisionNode;
use madong\workflow\model\builder\DecisionNodeBuilder;

class DecisionParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        $decisionBuilder = new DecisionNodeBuilder();
        $decisionBuilder->copyFrom($this->builder);

        $decisionBuilder
            ->expr($this->getStringProperty($lfNode, 'expr'))
            ->handleClass($this->getStringProperty($lfNode, 'handle_class'));

        $this->builder = $decisionBuilder;
    }

    public function build(): DecisionNode
    {
        if ($this->builder instanceof DecisionNodeBuilder) {
            return $this->builder->build();
        }
        throw new \LogicException('DecisionParser builder 未正确初始化');
    }
}

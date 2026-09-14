<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\SubProcessNode;
use madong\workflow\model\builder\SubProcessNodeBuilder;

class SubProcessParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        $subBuilder = new SubProcessNodeBuilder();
        $subBuilder->copyFrom($this->builder);

        $subBuilder
            ->processDefineId($this->getStringProperty($lfNode, 'process_define_id'))
            ->version((int) ($this->getProperty($lfNode, 'version') ?? 0));

        $this->builder = $subBuilder;
    }

    public function build(): SubProcessNode
    {
        if ($this->builder instanceof SubProcessNodeBuilder) {
            return $this->builder->build();
        }
        throw new \LogicException('SubProcessParser builder 未正确初始化');
    }
}

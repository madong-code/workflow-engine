<?php

namespace madong\workflow\model\builder;

use madong\workflow\model\nodes\DecisionNode;

class DecisionNodeBuilder extends NodeBuilder
{
    private ?string $expr = null;
    private ?string $handleClass = null;

    public function expr(?string $v): self { $this->expr = $v; return $this; }
    public function handleClass(?string $v): self { $this->handleClass = $v; return $this; }

    public function build(): DecisionNode
    {
        return new DecisionNode(
            ...$this->buildBase(),
            expr: $this->expr,
            handleClass: $this->handleClass,
        );
    }
}

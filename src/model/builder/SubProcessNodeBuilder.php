<?php

namespace madong\workflow\model\builder;

use madong\workflow\model\nodes\SubProcessNode;

class SubProcessNodeBuilder extends NodeBuilder
{
    private ?string $processDefineId = null;
    private int $version = 0;

    public function processDefineId(?string $v): self { $this->processDefineId = $v; return $this; }
    public function version(int $v): self { $this->version = $v; return $this; }

    public function build(): SubProcessNode
    {
        return new SubProcessNode(
            ...$this->buildBase(),
            processDefineId: $this->processDefineId,
            version: $this->version,
        );
    }
}

<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\context\ExecutionContext;

class SubProcessNode extends NodeModel
{
    public function __construct(
        string  $name,
        string  $displayName = '',
        \madong\workflow\model\vo\Layout $layout = new \madong\workflow\model\vo\Layout(0, 0, 0, 0),
        array   $inputs = [],
        array   $outputs = [],
        ?string $preInterceptors = null,
        ?string $postInterceptors = null,
        public readonly ?string $processDefineId = null,
        public readonly int     $version = 0,
    ) {
        parent::__construct($name, $displayName, $layout, $inputs, $outputs, $preInterceptors, $postInterceptors);
    }

    public function execute(ExecutionContext $ctx): void
    {
        $handler = new \madong\workflow\handler\StartSubProcessHandler($this);
        $handler->handle($ctx);
    }
}

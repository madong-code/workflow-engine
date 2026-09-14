<?php

namespace madong\workflow\model;

use madong\workflow\model\vo\Layout;
use madong\workflow\context\ExecutionContext;

abstract class NodeModel extends BaseModel
{
    public function __construct(
        string  $name,
        string  $displayName = '',
        public readonly Layout $layout = new Layout(0, 0, 0, 0),
        public array            $inputs = [],
        public array            $outputs = [],
        public readonly ?string $preInterceptors = null,
        public readonly ?string $postInterceptors = null,
    ) {
        parent::__construct($name, $displayName);
    }

    /**
     * 节点执行入口
     */
    abstract public function execute(ExecutionContext $ctx): void;

    /**
     * 获取所有输出边
     * @return TransitionModel[]
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * 获取所有输入边
     * @return TransitionModel[]
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }
}

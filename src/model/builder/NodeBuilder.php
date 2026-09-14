<?php

namespace madong\workflow\model\builder;

use madong\workflow\model\NodeModel;
use madong\workflow\model\TransitionModel;
use madong\workflow\model\vo\Layout;

class NodeBuilder
{
    protected string $name = '';
    protected string $displayName = '';
    protected Layout $layout;
    protected array $inputs = [];
    protected array $outputs = [];
    protected ?string $preInterceptors = null;
    protected ?string $postInterceptors = null;
    protected ?string $customClazz = null;
    protected ?string $customMethodName = null;
    protected ?string $customArgs = null;
    protected ?string $customVar = null;

    public function __construct()
    {
        $this->layout = new Layout(0, 0, 0, 0);
    }

    public function name(string $v): self { $this->name = $v; return $this; }
    public function displayName(string $v): self { $this->displayName = $v; return $this; }
    public function layout(Layout $v): self { $this->layout = $v; return $this; }
    public function inputs(array $v): self { $this->inputs = $v; return $this; }
    public function outputs(array $v): self { $this->outputs = $v; return $this; }
    public function addInput(TransitionModel $t): self { $this->inputs[] = $t; return $this; }
    public function addOutput(TransitionModel $t): self { $this->outputs[] = $t; return $this; }
    public function preInterceptors(?string $v): self { $this->preInterceptors = $v; return $this; }
    public function postInterceptors(?string $v): self { $this->postInterceptors = $v; return $this; }
    public function clazz(?string $v): self { $this->customClazz = $v; return $this; }
    public function methodName(?string $v): self { $this->customMethodName = $v; return $this; }
    public function args(?string $v): self { $this->customArgs = $v; return $this; }
    public function var(?string $v): self { $this->customVar = $v; return $this; }

    /**
     * 从另一个 NodeBuilder 复制基础属性
     */
    public function copyFrom(NodeBuilder $source): self
    {
        $this->name = $source->name;
        $this->displayName = $source->displayName;
        $this->layout = $source->layout;
        $this->inputs = $source->inputs;
        $this->outputs = $source->outputs;
        $this->preInterceptors = $source->preInterceptors;
        $this->postInterceptors = $source->postInterceptors;
        return $this;
    }

    protected function buildBase(): array
    {
        return [
            $this->name, $this->displayName, $this->layout,
            $this->inputs, $this->outputs,
            $this->preInterceptors, $this->postInterceptors,
        ];
    }

    public function buildStartNode(): \madong\workflow\model\nodes\StartNode
    {
        return new \madong\workflow\model\nodes\StartNode(...$this->buildBase());
    }

    public function buildEndNode(): \madong\workflow\model\nodes\EndNode
    {
        return new \madong\workflow\model\nodes\EndNode(...$this->buildBase());
    }

    public function buildForkNode(): \madong\workflow\model\nodes\ForkNode
    {
        return new \madong\workflow\model\nodes\ForkNode(...$this->buildBase());
    }

    public function buildJoinNode(): \madong\workflow\model\nodes\JoinNode
    {
        return new \madong\workflow\model\nodes\JoinNode(...$this->buildBase());
    }

    public function buildCustomNode(): \madong\workflow\model\nodes\CustomNode
    {
        return new \madong\workflow\model\nodes\CustomNode(
            name: $this->name,
            displayName: $this->displayName,
            layout: $this->layout,
            inputs: $this->inputs,
            outputs: $this->outputs,
            preInterceptors: $this->preInterceptors,
            postInterceptors: $this->postInterceptors,
            clazz: $this->customClazz,
            methodName: $this->customMethodName,
            args: $this->customArgs,
            var: $this->customVar,
        );
    }
}

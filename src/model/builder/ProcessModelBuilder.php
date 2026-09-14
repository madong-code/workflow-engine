<?php

namespace madong\workflow\model\builder;

use madong\workflow\model\NodeModel;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\model\ProcessModel;

class ProcessModelBuilder
{
    private string $name = '';
    private string $displayName = '';
    private string $type = '';
    private string $instanceUrl = '';
    private string $expireTime = '';
    private string $instanceNoClass = '';
    private ?string $preInterceptors = null;
    private ?string $postInterceptors = null;
    private array $nodes = [];
    private array $tasks = [];

    public function name(string $v): self { $this->name = $v; return $this; }
    public function displayName(string $v): self { $this->displayName = $v; return $this; }
    public function type(string $v): self { $this->type = $v; return $this; }
    public function instanceUrl(string $v): self { $this->instanceUrl = $v; return $this; }
    public function expireTime(string $v): self { $this->expireTime = $v; return $this; }
    public function instanceNoClass(string $v): self { $this->instanceNoClass = $v; return $this; }
    public function preInterceptors(?string $v): self { $this->preInterceptors = $v; return $this; }
    public function postInterceptors(?string $v): self { $this->postInterceptors = $v; return $this; }

    public function addNode(NodeModel $node): self
    {
        $this->nodes[] = $node;
        if ($node instanceof TaskNode) {
            $this->tasks[] = $node;
        }
        return $this;
    }

    public function nodes(array $v): self { $this->nodes = $v; return $this; }

    public function build(): ProcessModel
    {
        return new ProcessModel(
            $this->name, $this->displayName, $this->type,
            $this->instanceUrl, $this->expireTime, $this->instanceNoClass,
            $this->preInterceptors, $this->postInterceptors,
            $this->nodes, $this->tasks,
        );
    }
}

<?php

namespace madong\workflow\model;

class ProcessModel extends BaseModel
{
    /** @var NodeModel[] */
    private array $nodes = [];

    /** @var NodeModel[] TaskNode 实例列表 */
    private array $tasks = [];

    public function __construct(
        string  $name,
        string  $displayName = '',
        public readonly string $type = '',
        public readonly string $instanceUrl = '',
        public readonly string $expireTime = '',
        public readonly string $instanceNoClass = '',
        public readonly ?string $preInterceptors = null,
        public readonly ?string $postInterceptors = null,
        array $nodes = [],
        array $tasks = [],
    ) {
        parent::__construct($name, $displayName);
        $this->nodes = $nodes;
        $this->tasks = $tasks;
    }

    public function getStartNode(): ?NodeModel
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof nodes\StartNode) {
                return $node;
            }
        }
        return null;
    }

    public function getEndNode(): ?NodeModel
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof nodes\EndNode) {
                return $node;
            }
        }
        return null;
    }

    public function getNode(string $name): ?NodeModel
    {
        foreach ($this->nodes as $node) {
            if ($node->name === $name) {
                return $node;
            }
        }
        return null;
    }

    /**
     * @return NodeModel[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return NodeModel[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function addNode(NodeModel $node): void
    {
        $this->nodes[] = $node;
        if ($node instanceof nodes\TaskNode) {
            $this->tasks[] = $node;
        }
    }
}

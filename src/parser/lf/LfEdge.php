<?php

namespace madong\workflow\parser\lf;

class LfEdge
{
    public function __construct(
        public readonly string $id,
        public readonly string $sourceNodeId,
        public readonly string $targetNodeId,
        public readonly ?string $type = null,
        public readonly ?string $text = null,
        public readonly ?object $properties = null,
    ) {}

    public function getId(): string { return $this->id; }
    public function getSourceNodeId(): string { return $this->sourceNodeId; }
    public function getTargetNodeId(): string { return $this->targetNodeId; }

    public function getProperties(): ?object
    {
        return $this->properties;
    }
}

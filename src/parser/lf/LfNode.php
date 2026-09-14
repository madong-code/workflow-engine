<?php

namespace madong\workflow\parser\lf;

class LfNode
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly float $x = 0,
        public readonly float $y = 0,
        public readonly ?string $text = null,
        public readonly ?object $properties = null,
    ) {}

    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getText(): ?string { return $this->text; }

    public function getProperties(): ?object
    {
        return $this->properties;
    }
}

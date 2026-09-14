<?php

namespace madong\workflow\libs\utils;

class StringBuilder
{
    private array $parts = [];
    private string $separator;

    public function __construct(string $separator = '')
    {
        $this->separator = $separator;
    }

    public function append(string $part): self
    {
        $this->parts[] = $part;
        return $this;
    }

    public function build(): string
    {
        return implode($this->separator, $this->parts);
    }

    public function __toString(): string
    {
        return $this->build();
    }
}

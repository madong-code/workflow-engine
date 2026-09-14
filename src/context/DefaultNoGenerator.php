<?php

namespace madong\workflow\context;

class DefaultNoGenerator
{
    private int $sequence = 0;

    public function generate(string $prefix = ''): string
    {
        return $prefix . date('YmdHis') . str_pad((string)(++$this->sequence), 4, '0', STR_PAD_LEFT);
    }

    public function generateProcessInstanceNo(): string
    {
        return $this->generate('PI_');
    }

    public function generateTaskNo(): string
    {
        return $this->generate('TK_');
    }
}

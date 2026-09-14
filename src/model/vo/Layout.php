<?php

namespace madong\workflow\model\vo;

final readonly class Layout
{
    public function __construct(
        public float $x = 0,
        public float $y = 0,
        public float $width = 0,
        public float $height = 0,
    ) {}
}

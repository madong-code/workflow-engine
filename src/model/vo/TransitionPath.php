<?php

namespace madong\workflow\model\vo;

final readonly class TransitionPath
{
    public function __construct(
        public float $startX = 0,
        public float $startY = 0,
        public float $endX = 0,
        public float $endY = 0,
        public array  $points = [],
    ) {}
}

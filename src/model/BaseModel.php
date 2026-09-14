<?php

namespace madong\workflow\model;

abstract class BaseModel
{
    public function __construct(
        public readonly string $name,
        public readonly string $displayName = '',
    ) {}
}

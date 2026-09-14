<?php

namespace madong\workflow\expression;

use madong\workflow\context\ProcessVariables;

interface ExpressionEngine
{
    public function evaluate(string $expr, ProcessVariables $variables): mixed;

    public function evaluateToBool(string $expr, ProcessVariables $variables): bool;
}

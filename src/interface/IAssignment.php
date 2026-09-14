<?php

namespace madong\workflow\interface;

use madong\workflow\context\ExecutionContext;

interface IAssignment
{
    public function assign(ExecutionContext $ctx): ?string;
}

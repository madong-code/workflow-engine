<?php

namespace madong\workflow\handler;

use madong\workflow\context\ExecutionContext;

interface Handler
{
    public function handle(ExecutionContext $ctx): void;
}

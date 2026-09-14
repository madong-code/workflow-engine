<?php

namespace madong\workflow\aop;

use madong\workflow\context\ExecutionContext;
use madong\workflow\model\NodeModel;

class AspectContext
{
    public bool $shouldProceed = true;

    public function __construct(
        public readonly JoinPoint       $joinPoint,
        public readonly ExecutionContext $executionContext,
        public readonly ?NodeModel      $node = null,
        public readonly ?string         $nodeName = null,
        public readonly ?\Throwable     $throwable = null,
        public readonly mixed           $returnValue = null,
    ) {}

    public function getProcessInstanceId(): string
    {
        return $this->executionContext->processInstanceId;
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->executionContext->getVariable($key, $default);
    }
}

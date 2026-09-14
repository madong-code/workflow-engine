<?php

namespace madong\workflow\aop;

interface Aspect
{
    public function pointcut(): JoinPoint;
    public function adviceType(): AdviceType;
    public function priority(): int;
    public function name(): string;

    public function invoke(AspectContext $ctx, ?callable $proceed = null): void;
}

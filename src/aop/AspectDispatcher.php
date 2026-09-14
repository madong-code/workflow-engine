<?php

namespace madong\workflow\aop;

use madong\workflow\exception\FlowException;

class AspectDispatcher
{
    /** @var array<string, Aspect[]> */
    private array $aspects = [];
    private bool $traceEnabled = false;

    public function register(Aspect $aspect): void
    {
        $key = $aspect->pointcut()->value;
        foreach ($this->aspects[$key] ?? [] as $existing) {
            if ($existing->name() === $aspect->name()) {
                throw new FlowException("切面 [{$aspect->name()}] 已存在");
            }
        }
        $this->aspects[$key][] = $aspect;
        usort($this->aspects[$key], fn($a, $b) => $a->priority() <=> $b->priority());
    }

    public function registerBatch(array $aspects): void
    {
        foreach ($aspects as $a) {
            $this->register($a);
        }
    }

    public function unregister(string $name): void
    {
        foreach ($this->aspects as &$list) {
            $list = array_values(array_filter($list, fn($a) => $a->name() !== $name));
        }
    }

    public function getAspects(JoinPoint $jp): array
    {
        return $this->aspects[$jp->value] ?? [];
    }

    public function dispatch(JoinPoint $jp, AspectContext $ctx, callable $proceed): mixed
    {
        $all = $this->aspects[$jp->value] ?? [];

        $before     = array_filter($all, fn($a) => $a->adviceType() === AdviceType::BEFORE);
        $around     = array_filter($all, fn($a) => $a->adviceType() === AdviceType::AROUND);
        $after      = array_filter($all, fn($a) => $a->adviceType() === AdviceType::AFTER);
        $afterReturn = array_filter($all, fn($a) => $a->adviceType() === AdviceType::AFTER_RETURN);
        $afterThrow = array_filter($all, fn($a) => $a->adviceType() === AdviceType::AFTER_THROW);

        // 1. BEFORE
        foreach ($before as $aspect) {
            $this->invokeTraced($aspect, $ctx);
            if (!$ctx->shouldProceed) {
                AspectTrace::record($aspect->name(), $jp, 0, 'INTERRUPTED');
                return null;
            }
        }

        // 2. AROUND 洋葱链
        $chain = $proceed;
        foreach (array_reverse(array_values($around)) as $aspect) {
            $inner = $chain;
            $chain = fn() => $this->invokeTraced($aspect, $ctx, $inner);
        }

        $result = null;
        $throwable = null;
        try {
            $result = $chain();
        } catch (\Throwable $e) {
            $throwable = $e;
        }

        // 3. AFTER_THROW
        if ($throwable !== null) {
            $throwCtx = new AspectContext(
                $jp, $ctx->executionContext, $ctx->node, $ctx->nodeName, $throwable
            );
            foreach ($afterThrow as $aspect) {
                $this->invokeTraced($aspect, $throwCtx);
            }
            throw $throwable;
        }

        // 4. AFTER_RETURN
        $returnCtx = new AspectContext(
            $jp, $ctx->executionContext, $ctx->node, $ctx->nodeName, null, $result
        );
        foreach ($afterReturn as $aspect) {
            $this->invokeTraced($aspect, $returnCtx);
        }

        // 5. AFTER
        foreach ($after as $aspect) {
            $this->invokeTraced($aspect, $ctx);
        }

        return $result;
    }

    private function invokeTraced(Aspect $aspect, AspectContext $ctx, ?callable $proceed = null): mixed
    {
        $start = microtime(true);
        try {
            return $aspect->invoke($ctx, $proceed);
        } finally {
            if ($this->traceEnabled) {
                AspectTrace::record($aspect->name(), $aspect->pointcut(), (microtime(true) - $start) * 1000);
            }
        }
    }

    public function setTraceEnabled(bool $v): void { $this->traceEnabled = $v; }
}

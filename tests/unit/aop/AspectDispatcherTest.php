<?php

namespace madong\workflow\tests\unit\aop;

use PHPUnit\Framework\TestCase;
use madong\workflow\aop\{AspectDispatcher, Aspect, JoinPoint, AdviceType, AspectPriority, AspectContext};

class AspectDispatcherTest extends TestCase
{
    public function testBeforeAspectCanInterrupt(): void
    {
        $dispatcher = new AspectDispatcher();
        $dispatcher->register(new class implements Aspect {
            public function pointcut(): JoinPoint { return JoinPoint::NODE_PRE_EXECUTE; }
            public function adviceType(): AdviceType { return AdviceType::BEFORE; }
            public function priority(): int { return 10; }
            public function name(): string { return 'test.interrupt'; }
            public function invoke(AspectContext $ctx, ?callable $proceed = null): void {
                $ctx->shouldProceed = false;
            }
        });

        $called = false;
        $ctx = new AspectContext(JoinPoint::NODE_PRE_EXECUTE, $this->createMock(\madong\workflow\context\ExecutionContext::class));
        $dispatcher->dispatch(JoinPoint::NODE_PRE_EXECUTE, $ctx, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called, '目标方法不应被执行');
    }

    public function testAroundAspectWrapsTarget(): void
    {
        $dispatcher = new AspectDispatcher();
        $log = [];

        $dispatcher->register(new class($log) implements Aspect {
            public function __construct(private array &$log) {}
            public function pointcut(): JoinPoint { return JoinPoint::NODE_AROUND_EXECUTE; }
            public function adviceType(): AdviceType { return AdviceType::AROUND; }
            public function priority(): int { return 10; }
            public function name(): string { return 'test.around'; }
            public function invoke(AspectContext $ctx, ?callable $proceed = null): void {
                $this->log[] = 'before';
                if ($proceed) $proceed();
                $this->log[] = 'after';
            }
        });

        $ctx = new AspectContext(JoinPoint::NODE_AROUND_EXECUTE, $this->createMock(\madong\workflow\context\ExecutionContext::class));
        $dispatcher->dispatch(JoinPoint::NODE_AROUND_EXECUTE, $ctx, function () use (&$log) {
            $log[] = 'target';
        });

        $this->assertEquals(['before', 'target', 'after'], $log);
    }
}

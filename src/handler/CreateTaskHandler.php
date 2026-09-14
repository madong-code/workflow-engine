<?php

namespace madong\workflow\handler;

use madong\workflow\context\ExecutionContext;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\enum\PerformType;
use madong\workflow\aop\{JoinPoint, AspectContext};
use madong\workflow\enum\EventType;

class CreateTaskHandler implements Handler
{
    public function __construct(
        private readonly TaskNode $taskNode,
    ) {}

    public function handle(ExecutionContext $ctx): void
    {
        $taskService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessTaskService::class
        );

        $tasks = $taskService->createTask($this->taskNode, $ctx);
        $ctx->addTasks($tasks);

        // AOP: TASK_CREATE 切面
        $dispatcher = $ctx->engine->getAspectDispatcher();
        foreach ($tasks as $task) {
            $aspectCtx = new AspectContext(JoinPoint::TASK_CREATE, $ctx, $this->taskNode, $this->taskNode->name);
            $dispatcher->dispatch(JoinPoint::TASK_CREATE, $aspectCtx, fn() => null);
        }

        // 事件: 任务启动
        $eventBus = $ctx->engine->getEventBus();
        foreach ($tasks as $task) {
            $eventBus->publish(EventType::TASK_START->value, $task);
        }
    }
}

<?php

namespace madong\workflow\tests\unit\handler;

use PHPUnit\Framework\TestCase;
use madong\workflow\handler\CreateTaskHandler;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\context\ExecutionContext;
use madong\workflow\Engine;
use madong\workflow\interface\services\IProcessTaskService;
use madong\workflow\interface\model\IProcessTask;
use madong\workflow\enum\TaskState;

class CreateTaskHandlerTest extends TestCase
{
    public function testHandleCreatesTaskAndAddsToContext(): void
    {
        $taskNode = new TaskNode(name: 'approve', displayName: '审批任务', assignee: '${manager}');

        $createdTask = new class implements IProcessTask {
            public function getId(): string { return '1'; }
            public function getProcessInstanceId(): string { return 'inst-1'; }
            public function getTaskName(): string { return 'approve'; }
            public function getTaskState(): string { return TaskState::DOING->value; }
            public function getTaskType(): string { return ''; }
            public function getPerformType(): string { return ''; }
            public function getOperator(): ?string { return null; }
            public function getCreateTime(): int { return 0; }
            public function getData(string $key, mixed $default = null): mixed { return $default; }
        };

        $taskService = $this->createMock(IProcessTaskService::class);
        $taskService->expects($this->once())
            ->method('createTask')
            ->with($taskNode, $this->anything())
            ->willReturn([$createdTask]);

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->with(IProcessTaskService::class)
            ->willReturn($taskService);
        $engine->method('getAspectDispatcher')->willReturn(
            $this->createMock(\madong\workflow\aop\AspectDispatcher::class)
        );
        $engine->method('getEventBus')->willReturn(
            $this->createMock(\madong\workflow\event\EventBus::class)
        );

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
        );

        $handler = new CreateTaskHandler($taskNode);
        $handler->handle($ctx);

        $tasks = $ctx->getCreatedTasks();
        $this->assertCount(1, $tasks);
        $this->assertEquals('approve', $tasks[0]->getTaskName());
    }
}

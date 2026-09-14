<?php

namespace madong\workflow\tests\unit\handler;

use PHPUnit\Framework\TestCase;
use madong\workflow\handler\MergeBranchHandler;
use madong\workflow\model\nodes\{ForkNode, JoinNode, TaskNode};
use madong\workflow\model\TransitionModel;
use madong\workflow\context\{ExecutionContext, ProcessVariables, ExecutionResult};
use madong\workflow\Engine;
use madong\workflow\interface\services\IProcessTaskService;
use madong\workflow\interface\model\IProcessTask;
use madong\workflow\enum\TaskState;

class MergeBranchHandlerTest extends TestCase
{
    public function testMergedWhenNoActiveTasks(): void
    {
        $taskA = new TaskNode(name: 'task_a', displayName: '任务A', assignee: 'user_a');
        $taskB = new TaskNode(name: 'task_b', displayName: '任务B', assignee: 'user_b');
        $join  = new JoinNode(name: 'join', displayName: '合并');

        // 构建: task_a -> join, task_b -> join
        $t1 = new TransitionModel(name: 'e1', source: $taskA, target: $join);
        $t2 = new TransitionModel(name: 'e2', source: $taskB, target: $join);
        $join->inputs = [$t1, $t2];

        // task_a, task_b 的 inputs 来自 fork
        $fork = new ForkNode(name: 'fork', displayName: '分支');
        $t3 = new TransitionModel(name: 'e3', source: $fork, target: $taskA);
        $t4 = new TransitionModel(name: 'e4', source: $fork, target: $taskB);
        $taskA->inputs = [$t3];
        $taskB->inputs = [$t4];

        $taskService = $this->createMock(IProcessTaskService::class);
        $taskService->method('getDoingTaskList')
            ->willReturn([]); // 没有进行中的任务

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->with(IProcessTaskService::class)
            ->willReturn($taskService);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user001',
            engine: $engine,
        );

        $handler = new MergeBranchHandler($join);
        $handler->handle($ctx);

        $this->assertTrue($ctx->result->isMerged());
    }

    public function testNotMergedWhenActiveTasksRemain(): void
    {
        $taskA = new TaskNode(name: 'task_a', displayName: '任务A', assignee: 'user_a');
        $taskB = new TaskNode(name: 'task_b', displayName: '任务B', assignee: 'user_b');
        $join  = new JoinNode(name: 'join', displayName: '合并');

        $t1 = new TransitionModel(name: 'e1', source: $taskA, target: $join);
        $t2 = new TransitionModel(name: 'e2', source: $taskB, target: $join);
        $join->inputs = [$t1, $t2];

        $fork = new ForkNode(name: 'fork', displayName: '分支');
        $t3 = new TransitionModel(name: 'e3', source: $fork, target: $taskA);
        $t4 = new TransitionModel(name: 'e4', source: $fork, target: $taskB);
        $taskA->inputs = [$t3];
        $taskB->inputs = [$t4];

        // 还有一个进行中的任务
        $doingTask = new class implements IProcessTask {
            public function getId(): string { return '1'; }
            public function getProcessInstanceId(): string { return 'inst-1'; }
            public function getTaskName(): string { return 'task_b'; }
            public function getTaskState(): string { return TaskState::DOING->value; }
            public function getTaskType(): string { return ''; }
            public function getPerformType(): string { return ''; }
            public function getOperator(): ?string { return null; }
            public function getCreateTime(): int { return 0; }
            public function getData(string $key, mixed $default = null): mixed { return $default; }
        };

        $taskService = $this->createMock(IProcessTaskService::class);
        $taskService->method('getDoingTaskList')
            ->willReturn([$doingTask]);

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->with(IProcessTaskService::class)
            ->willReturn($taskService);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user001',
            engine: $engine,
        );

        $handler = new MergeBranchHandler($join);
        $handler->handle($ctx);

        $this->assertFalse($ctx->result->isMerged());
    }
}

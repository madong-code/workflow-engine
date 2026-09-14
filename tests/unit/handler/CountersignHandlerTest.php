<?php

namespace madong\workflow\tests\unit\handler;

use PHPUnit\Framework\TestCase;
use madong\workflow\handler\CountersignHandler;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\context\{ExecutionContext, ProcessVariables, ExecutionResult};
use madong\workflow\Engine;
use madong\workflow\interface\services\IProcessTaskService;
use madong\workflow\interface\model\IProcessTask;
use madong\workflow\enum\{CountersignType, ProcessConst};

class CountersignHandlerTest extends TestCase
{
    private function makeCurrentTask(string $taskName): IProcessTask
    {
        $task = $this->createMock(IProcessTask::class);
        $task->method('getTaskName')->willReturn($taskName);
        return $task;
    }

    private function makeEngine($taskService, $expressionEngine): Engine
    {
        $instanceService = $this->createMock(\madong\workflow\interface\services\IProcessInstanceService::class);

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->willReturnCallback(function ($class) use ($taskService, $instanceService) {
                if ($class === IProcessTaskService::class) return $taskService;
                if ($class === \madong\workflow\interface\services\IProcessInstanceService::class) return $instanceService;
                return null;
            });
        $engine->method('getExpressionEngine')
            ->willReturn($expressionEngine);
        return $engine;
    }

    public function testParallelCountersignMergesWhenNoActiveTasks(): void
    {
        $taskNode = new TaskNode(
            name: 'approve',
            displayName: '会签审批',
            assignee: '${manager}',
            countersignType: CountersignType::PARALLEL,
        );

        $taskService = $this->createMock(IProcessTaskService::class);
        $taskService->method('getDoingTaskList')
            ->willReturn([]); // 无进行中任务 -> 合并完成

        $expressionEngine = $this->createMock(\madong\workflow\expression\ExpressionEngine::class);

        $engine = $this->makeEngine($taskService, $expressionEngine);

        $variables = new ProcessVariables();
        $variables->set(ProcessConst::COUNTERSIGN_PREFIX . 'approve_' . ProcessConst::OPERATOR_LIST, ['user1', 'user2']);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user1',
            engine: $engine,
            variables: $variables,
            processTask: $this->makeCurrentTask('approve'),
        );

        $handler = new CountersignHandler($taskNode);
        $handler->handle($ctx);

        $this->assertTrue($ctx->result->isMerged());
    }

    public function testParallelCountersignNotMergedWhenActiveTasksRemain(): void
    {
        $taskNode = new TaskNode(
            name: 'approve',
            displayName: '会签审批',
            assignee: '${manager}',
            countersignType: CountersignType::PARALLEL,
        );

        $doingTask = new \stdClass();
        $doingTask->id = '1';

        $taskService = $this->createMock(IProcessTaskService::class);
        $taskService->method('getDoingTaskList')
            ->willReturn([$doingTask]); // 仍有任务

        $expressionEngine = $this->createMock(\madong\workflow\expression\ExpressionEngine::class);

        $engine = $this->makeEngine($taskService, $expressionEngine);

        $variables = new ProcessVariables();
        $variables->set(ProcessConst::COUNTERSIGN_PREFIX . 'approve_' . ProcessConst::OPERATOR_LIST, ['user1', 'user2']);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user1',
            engine: $engine,
            variables: $variables,
            processTask: $this->makeCurrentTask('approve'),
        );

        $handler = new CountersignHandler($taskNode);
        $handler->handle($ctx);

        $this->assertFalse($ctx->result->isMerged());
    }

    public function testSequentialCountersignMergesOnLastOperator(): void
    {
        $taskNode = new TaskNode(
            name: 'approve',
            displayName: '串行会签',
            assignee: '${manager}',
            countersignType: CountersignType::SEQUENTIAL,
        );

        $taskService = $this->createMock(IProcessTaskService::class);
        $expressionEngine = $this->createMock(\madong\workflow\expression\ExpressionEngine::class);

        $engine = $this->makeEngine($taskService, $expressionEngine);

        $operators = ['user1', 'user2', 'user3'];
        $variables = new ProcessVariables();
        $variables->set(ProcessConst::COUNTERSIGN_PREFIX . 'approve_' . ProcessConst::OPERATOR_LIST, $operators);

        // 当前操作者是最后一个 (user3, index=2)
        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user3',
            engine: $engine,
            variables: $variables,
            processTask: $this->makeCurrentTask('approve'),
        );

        $handler = new CountersignHandler($taskNode);
        $handler->handle($ctx);

        $this->assertTrue($ctx->result->isMerged());
    }

    public function testSequentialCountersignNotMergedOnNonLastOperator(): void
    {
        $taskNode = new TaskNode(
            name: 'approve',
            displayName: '串行会签',
            assignee: '${manager}',
            countersignType: CountersignType::SEQUENTIAL,
        );

        $taskService = $this->createMock(IProcessTaskService::class);
        $expressionEngine = $this->createMock(\madong\workflow\expression\ExpressionEngine::class);

        $engine = $this->makeEngine($taskService, $expressionEngine);

        $operators = ['user1', 'user2', 'user3'];
        $variables = new ProcessVariables();
        $variables->set(ProcessConst::COUNTERSIGN_PREFIX . 'approve_' . ProcessConst::OPERATOR_LIST, $operators);

        // 当前操作者是第一个 (user1, index=0)
        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user1',
            engine: $engine,
            variables: $variables,
            processTask: $this->makeCurrentTask('approve'),
        );

        $handler = new CountersignHandler($taskNode);
        $handler->handle($ctx);

        $this->assertFalse($ctx->result->isMerged());
    }
}

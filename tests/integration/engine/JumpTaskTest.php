<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\{ProcessState, TaskState};
use madong\workflow\interface\services\IProcessTaskService;

class JumpTaskTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../fixture/data/simple_leave.json'
        );
    }

    public function testJumpTaskToApproveNode(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $applyTask = $tasks[0];

        // 从 apply 跳转到 approve
        $created = $this->engine->jumpTask($applyTask->getId(), 'user001', 'approve');
        $this->assertNotEmpty($created);
        $this->assertEquals('approve', $created[0]->getData('task_name'));

        // apply 任务已结束
        $finishedTask = $taskService->getById($applyTask->getId());
        $this->assertEquals(TaskState::FINISHED->value, $finishedTask->getData('task_state'));
    }

    public function testJumpTaskBackToPreviousNode(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);

        // 完成 apply -> approve
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->engine->completeTask($tasks[0]->getId(), 'user001');

        // 获取 approve 任务
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $approveTask = $tasks[0];

        // 从 approve 跳回 apply
        $created = $this->engine->jumpTask($approveTask->getId(), 'manager001', 'apply');
        $this->assertNotEmpty($created);
        $this->assertEquals('apply', $created[0]->getData('task_name'));
    }

    public function testRejectToFirst(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);

        // 完成 apply -> approve
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->engine->completeTask($tasks[0]->getId(), 'user001');

        // 获取 approve 任务
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $approveTask = $tasks[0];

        // 驳回到第一个任务节点（apply）
        $created = $this->engine->rejectToFirst($approveTask->getId(), 'manager001');
        $this->assertNotEmpty($created);
        $this->assertEquals('apply', $created[0]->getData('task_name'));
    }
}

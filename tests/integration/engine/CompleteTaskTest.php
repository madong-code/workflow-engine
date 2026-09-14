<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\{ProcessState, TaskState};
use madong\workflow\interface\services\IProcessTaskService;
use madong\workflow\interface\services\IProcessInstanceService;

class CompleteTaskTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../fixture/data/simple_leave.json'
        );
    }

    public function testCompleteFirstTaskCreatesNextTask(): void
    {
        // 启动流程 -> 生成 apply 任务
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->assertCount(1, $tasks);

        $applyTask = $tasks[0];
        $this->assertEquals('apply', $applyTask->getData('task_name'));

        // 完成 apply 任务 -> 生成 approve 任务
        $created = $this->engine->completeTask($applyTask->getId(), 'user001');
        $this->assertNotEmpty($created);
        $this->assertEquals('approve', $created[0]->getData('task_name'));

        // apply 任务已完成
        $finishedTask = $taskService->getById($applyTask->getId());
        $this->assertEquals(TaskState::FINISHED->value, $finishedTask->getData('task_state'));
    }

    public function testCompleteLastTaskFinishesProcess(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);

        // 完成 apply
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->engine->completeTask($tasks[0]->getId(), 'user001');

        // 完成 approve
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $approveTask = $tasks[0];
        $this->assertEquals('approve', $approveTask->getData('task_name'));

        $this->engine->completeTask($approveTask->getId(), 'manager001');

        // 验证流程已结束
        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $updatedInstance = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $updatedInstance->getData('state'));
    }

    public function testRejectToEndRejectsProcess(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');

        // 驳回
        $this->engine->rejectToEnd($tasks[0]->getId(), 'user001');

        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $updatedInstance = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::REJECTED->value, $updatedInstance->getData('state'));
    }
}

<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\{ProcessState, TaskState};
use madong\workflow\interface\services\{IProcessTaskService, IProcessInstanceService};

/**
 * 请假流程集成测试（json/wf-leave.json）
 *
 * 节点链路：start → apply → approveDept → d1(decision) → approveHr(请假<3天) / approveBoss(请假>=3天) → end
 */
class LeaveProcessTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../process/wf-leave.json'
        );
    }

    private function doingTasks(string $instanceId): array
    {
        $taskService = $this->engine->getService(IProcessTaskService::class);
        return $taskService->getDoingTaskList($instanceId, '');
    }

    public function testLeaveUnderThreeDaysGoesToHr(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', ['f_day' => 1]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->assertCount(1, $tasks);
        $this->assertEquals('apply', $tasks[0]->getData('task_name'));

        // 完成 apply → approveDept
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', ['f_day' => 1]);
        $tasks = $this->doingTasks($instance->getId());
        $this->assertEquals('approveDept', $tasks[0]->getData('task_name'));

        // 完成 approveDept → 决策分支（f_day<3 → approveHr）
        $this->engine->completeTask($tasks[0]->getId(), 'dept', ['f_day' => 1]);
        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(1, $tasks);
        $this->assertEquals('approveHr', $tasks[0]->getData('task_name'));
    }

    public function testLeaveThreeDaysOrMoreGoesToBoss(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', ['f_day' => 5]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $this->doingTasks($instance->getId());
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', ['f_day' => 5]);

        $tasks = $this->doingTasks($instance->getId());
        $this->engine->completeTask($tasks[0]->getId(), 'dept', ['f_day' => 5]);

        // f_day>=3 → approveBoss
        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(1, $tasks);
        $this->assertEquals('approveBoss', $tasks[0]->getData('task_name'));
    }

    public function testCompleteLeaveProcessFinishes(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', ['f_day' => 1]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $this->doingTasks($instance->getId());
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', ['f_day' => 1]);

        $tasks = $this->doingTasks($instance->getId());
        $this->engine->completeTask($tasks[0]->getId(), 'dept', ['f_day' => 1]);

        $tasks = $this->doingTasks($instance->getId());
        $this->assertEquals('approveHr', $tasks[0]->getData('task_name'));
        $this->engine->completeTask($tasks[0]->getId(), 'hr', ['f_day' => 1]);

        // 流程结束
        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));
    }
}

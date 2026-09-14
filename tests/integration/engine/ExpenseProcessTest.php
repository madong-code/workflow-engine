<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\ProcessState;
use madong\workflow\interface\services\{IProcessTaskService, IProcessInstanceService};

/**
 * 报销流程集成测试（json/wf-expense.json）
 *
 * 节点链路：start → apply → d1(decision) → end(金额<1000) / approveDept→approveBoss→approveFinance→end(金额>=1000)
 */
class ExpenseProcessTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../process/wf-expense.json'
        );
    }

    private function doingTasks(string $instanceId): array
    {
        $taskService = $this->engine->getService(IProcessTaskService::class);
        return $taskService->getDoingTaskList($instanceId, '');
    }

    public function testExpenseUnderThresholdGoesDirectlyToEnd(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', ['f_amount' => 500]);

        // 启动后为 apply 任务
        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(1, $tasks);
        $this->assertEquals('apply', $tasks[0]->getData('task_name'));

        // 完成 apply → 决策 f_amount<1000 → 直接 end
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', ['f_amount' => 500]);

        // 无待办任务，流程结束
        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(0, $tasks);

        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));
    }

    public function testExpenseOverThresholdGoesToDeptApproval(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', ['f_amount' => 5000]);

        $tasks = $this->doingTasks($instance->getId());
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', ['f_amount' => 5000]);

        // f_amount>=1000 → approveDept
        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(1, $tasks);
        $this->assertEquals('approveDept', $tasks[0]->getData('task_name'));

        // approveDept → approveBoss → approveFinance
        $this->engine->completeTask($tasks[0]->getId(), 'dept', ['f_amount' => 5000]);
        $tasks = $this->doingTasks($instance->getId());
        $this->assertEquals('approveBoss', $tasks[0]->getData('task_name'));

        $this->engine->completeTask($tasks[0]->getId(), 'boss', ['f_amount' => 5000]);
        $tasks = $this->doingTasks($instance->getId());
        $this->assertEquals('approveFinance', $tasks[0]->getData('task_name'));

        $this->engine->completeTask($tasks[0]->getId(), 'finance', ['f_amount' => 5000]);

        // 流程结束
        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));
    }
}

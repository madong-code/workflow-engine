<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\ProcessState;
use madong\workflow\interface\services\{IProcessTaskService, IProcessInstanceService};

/**
 * 会签流程集成测试（json/wf-countersign.json）
 *
 * 节点链路：start → apply → countersign(perform_type=ALL 三人并行会签) → merge(join) → end
 * 会签完成条件：#nrOfCompletedInstances==3，即三人全部完成才合并继续。
 */
class CountersignProcessTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../process/wf-countersign.json'
        );
    }

    private function doingTasks(string $instanceId): array
    {
        $taskService = $this->engine->getService(IProcessTaskService::class);
        return $taskService->getDoingTaskList($instanceId, '');
    }

    public function testCountersignCreatesMultipleTasks(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', [
            'operatorList' => ['o1', 'o2', 'o3'],
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(1, $tasks);
        $this->assertEquals('apply', $tasks[0]->getData('task_name'));

        // 完成 apply → 到达会签节点，创建 3 个会签任务
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', [
            'operatorList' => ['o1', 'o2', 'o3'],
        ]);

        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(3, $tasks);
        $names = array_unique(array_map(fn($t) => $t->getData('task_name'), $tasks));
        $this->assertCount(1, $names);
        $this->assertEquals('countersign', $names[0]);

        $operators = array_map(fn($t) => $t->getData('operator'), $tasks);
        sort($operators);
        $this->assertEquals(['o1', 'o2', 'o3'], $operators);
    }

    public function testCountersignMergesOnlyWhenAllDone(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', [
            'operatorList' => ['o1', 'o2', 'o3'],
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $this->doingTasks($instance->getId());
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', [
            'operatorList' => ['o1', 'o2', 'o3'],
        ]);

        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(3, $tasks);

        $instanceService = $this->engine->getService(IProcessInstanceService::class);

        // 完成前 2 个会签任务，流程不应结束（还有 1 个未完成）
        $this->engine->completeTask($tasks[0]->getId(), 'o1');
        $this->assertEquals(ProcessState::ACTIVE->value, $instanceService->getById($instance->getId())->getData('state'));

        $this->engine->completeTask($tasks[1]->getId(), 'o2');
        $this->assertEquals(ProcessState::ACTIVE->value, $instanceService->getById($instance->getId())->getData('state'));

        // 完成第 3 个 → 全部完成，合并继续到 end → 流程结束
        $this->engine->completeTask($tasks[2]->getId(), 'o3');
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));
    }
}

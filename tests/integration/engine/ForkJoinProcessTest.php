<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\ProcessState;
use madong\workflow\interface\services\{IProcessTaskService, IProcessInstanceService};

/**
 * 分支合并流程集成测试（json/wf-fork-join.json）
 *
 * 节点链路：start → fork → taskA/taskB/taskC（3 并行分支）→ join → end
 */
class ForkJoinProcessTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../process/wf-fork-join.json'
        );
    }

    private function doingTasks(string $instanceId): array
    {
        $taskService = $this->engine->getService(IProcessTaskService::class);
        return $taskService->getDoingTaskList($instanceId, '');
    }

    public function testForkCreatesThreeParallelTasks(): void
    {
        $instance = $this->engine->startProcess('1', 'start.operator');

        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(3, $tasks);

        $names = array_map(fn($t) => $t->getData('task_name'), $tasks);
        $this->assertContains('taskA', $names);
        $this->assertContains('taskB', $names);
        $this->assertContains('taskC', $names);
    }

    public function testJoinWaitsForAllBranches(): void
    {
        $instance = $this->engine->startProcess('1', 'start.operator');

        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(3, $tasks);

        // 只完成一个分支，流程不应结束
        $this->engine->completeTask($tasks[0]->getId(), $tasks[0]->getData('operator') ?: 'taskA');
        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $this->assertEquals(ProcessState::ACTIVE->value, $instanceService->getById($instance->getId())->getData('state'));
    }

    public function testJoinCompletesWhenAllBranchesDone(): void
    {
        $instance = $this->engine->startProcess('1', 'start.operator');

        $tasks = $this->doingTasks($instance->getId());
        $this->assertCount(3, $tasks);

        foreach ($tasks as $task) {
            $this->engine->completeTask($task->getId(), $task->getData('operator') ?: $task->getData('task_name'));
        }

        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));
    }
}

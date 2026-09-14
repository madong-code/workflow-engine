<?php

namespace madong\workflow\tests\integration\engine\forkjointest;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\{ProcessState, TaskState};
use madong\workflow\interface\services\{IProcessTaskService, IProcessInstanceService};

class ForkJoinTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../../fixture/data/fork_join.json'
        );
    }

    public function testForkCreatesParallelTasks(): void
    {
        $instance = $this->engine->startProcess('1', 'user001');

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');

        // Fork 后应同时创建 task_a 和 task_b 两个并行任务
        $this->assertCount(2, $tasks);

        $taskNames = array_map(fn($t) => $t->getData('task_name'), $tasks);
        $this->assertContains('task_a', $taskNames);
        $this->assertContains('task_b', $taskNames);
    }

    public function testJoinWaitsForAllBranches(): void
    {
        $instance = $this->engine->startProcess('1', 'user001');

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->assertCount(2, $tasks);

        // 只完成 task_a，流程不应结束（Join 等待 task_b）
        $taskA = array_values(array_filter($tasks, fn($t) => $t->getData('task_name') === 'task_a'))[0];
        $this->engine->completeTask($taskA->getId(), 'user_a');

        // 流程仍然活跃
        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $stillActive = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::ACTIVE->value, $stillActive->getData('state'));
    }

    public function testJoinCompletesWhenAllBranchesDone(): void
    {
        $instance = $this->engine->startProcess('1', 'user001');

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');

        // 完成 task_a
        $taskA = array_values(array_filter($tasks, fn($t) => $t->getData('task_name') === 'task_a'))[0];
        $this->engine->completeTask($taskA->getId(), 'user_a');

        // 完成 task_b -> Join 合并 -> 流程结束
        $taskB = array_values(array_filter($tasks, fn($t) => $t->getData('task_name') === 'task_b'))[0];
        $this->engine->completeTask($taskB->getId(), 'user_b');

        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));
    }
}

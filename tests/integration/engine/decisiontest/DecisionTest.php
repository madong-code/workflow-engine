<?php

namespace madong\workflow\tests\integration\engine\decisiontest;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\interface\services\IProcessTaskService;

class DecisionTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../../fixture/data/decision.json'
        );
    }

    public function testDecisionGoesToTaskAWhenAmountExceedsThreshold(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'amount' => 5000,
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');

        $this->assertCount(1, $tasks);
        $this->assertEquals('task_a', $tasks[0]->getData('task_name'));
        $this->assertEquals('user_a', $tasks[0]->getData('operator'));
    }

    public function testDecisionGoesToTaskBWhenAmountBelowThreshold(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'amount' => 500,
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');

        $this->assertCount(1, $tasks);
        $this->assertEquals('task_b', $tasks[0]->getData('task_name'));
        $this->assertEquals('user_b', $tasks[0]->getData('operator'));
    }
}

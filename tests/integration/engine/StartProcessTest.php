<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\ProcessState;

class StartProcessTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../fixture/data/simple_leave.json'
        );
    }

    public function testStartProcess(): void
    {
        $instance = $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $this->assertNotNull($instance);
        $this->assertEquals(ProcessState::ACTIVE->value, $instance->getData('state'));
    }

    public function testStartProcessCreatesTask(): void
    {
        $this->engine->startProcess('1', 'user001', [
            'initiator' => 'user001',
            'manager'   => 'manager001',
        ]);

        $taskService = $this->engine->getService(
            \madong\workflow\interface\services\IProcessTaskService::class
        );
        $tasks = $taskService->getDoingTaskList('1', '');
        $this->assertNotEmpty($tasks);
    }
}

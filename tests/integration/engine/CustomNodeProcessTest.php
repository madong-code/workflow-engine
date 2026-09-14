<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\tests\fixture\TestEngineFactory;
use madong\workflow\enum\ProcessState;
use madong\workflow\interface\services\{IProcessTaskService, IProcessInstanceService};

/**
 * 自定义节点流程集成测试（json/wf-custom-node.json）
 *
 * 节点链路：start → apply → custom1(AmountCalculateHandler.calculate，args=a,b，结果写 f_resultAmount) → end
 */
class CustomNodeProcessTest extends TestCase
{
    private \madong\workflow\Engine $engine;

    protected function setUp(): void
    {
        $this->engine = TestEngineFactory::createWithDefinition(
            __DIR__ . '/../../process/wf-custom-node.json'
        );
    }

    public function testCustomNodeWritesResultVariableAndFinishes(): void
    {
        $instance = $this->engine->startProcess('1', 'apply.operator', [
            'a' => 10,
            'b' => 20,
        ]);

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->assertCount(1, $tasks);
        $this->assertEquals('apply', $tasks[0]->getData('task_name'));

        // 完成 apply → custom1 执行 AmountCalculateHandler.calculate → 流程到 end
        $this->engine->completeTask($tasks[0]->getId(), 'apply.operator', ['a' => 10, 'b' => 20]);

        // 无待办任务，流程结束
        $tasks = $taskService->getDoingTaskList($instance->getId(), '');
        $this->assertCount(0, $tasks);

        $instanceService = $this->engine->getService(IProcessInstanceService::class);
        $finished = $instanceService->getById($instance->getId());
        $this->assertEquals(ProcessState::FINISHED->value, $finished->getData('state'));

        // custom 节点已将计算结果写入 f_resultAmount 变量
        $variable = $finished->getVariable();
        $this->assertArrayHasKey('f_resultAmount', $variable);
        $this->assertEquals(30, $variable['f_resultAmount']);
    }
}

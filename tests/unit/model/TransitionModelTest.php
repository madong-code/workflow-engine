<?php

namespace madong\workflow\tests\unit\model;

use PHPUnit\Framework\TestCase;
use madong\workflow\model\TransitionModel;
use madong\workflow\model\nodes\{StartNode, EndNode};
use madong\workflow\context\{ExecutionContext, ProcessVariables};
use madong\workflow\Engine;
use madong\workflow\expression\DefaultExpressionEngine;

class TransitionModelTest extends TestCase
{
    public function testEvaluateEnabledReturnsTrueWhenNoExpression(): void
    {
        $transition = new TransitionModel(name: 'e1', expr: null);

        $engine = $this->createMock(Engine::class);
        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
        );

        $this->assertTrue($transition->evaluateEnabled($ctx));
        $this->assertTrue($transition->enabled);
    }

    public function testEvaluateEnabledReturnsTrueWhenEmptyExpression(): void
    {
        $transition = new TransitionModel(name: 'e1', expr: '');

        $engine = $this->createMock(Engine::class);
        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
        );

        $this->assertTrue($transition->evaluateEnabled($ctx));
    }

    public function testEvaluateEnabledWithTrueExpression(): void
    {
        $transition = new TransitionModel(name: 'e1', expr: '${amount} > 1000');

        $expressionEngine = new DefaultExpressionEngine();
        $engine = $this->createMock(Engine::class);
        $engine->method('getExpressionEngine')
            ->willReturn($expressionEngine);

        $variables = new ProcessVariables();
        $variables->set('amount', 5000);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
            variables: $variables,
        );

        $this->assertTrue($transition->evaluateEnabled($ctx));
    }

    public function testEvaluateEnabledWithFalseExpression(): void
    {
        $transition = new TransitionModel(name: 'e1', expr: '${amount} > 1000');

        $expressionEngine = new DefaultExpressionEngine();
        $engine = $this->createMock(Engine::class);
        $engine->method('getExpressionEngine')
            ->willReturn($expressionEngine);

        $variables = new ProcessVariables();
        $variables->set('amount', 500);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
            variables: $variables,
        );

        $this->assertFalse($transition->evaluateEnabled($ctx));
    }

    public function testExecuteDelegatesToTargetNode(): void
    {
        $startNode = $this->createMock(StartNode::class);
        $startNode->expects($this->once())->method('execute');

        $transition = new TransitionModel(
            name: 'e1',
            source: $startNode,
            target: $startNode,
        );

        $engine = $this->createMock(Engine::class);
        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
        );

        $transition->execute($ctx);
    }

    public function testExecuteDoesNothingWhenNoTarget(): void
    {
        $transition = new TransitionModel(name: 'e1', target: null);

        $engine = $this->createMock(Engine::class);
        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: 0,
            operator: 'user001',
            engine: $engine,
        );

        // 不应抛异常
        $transition->execute($ctx);
        $this->assertTrue(true); // 到达此处即验证通过
    }
}

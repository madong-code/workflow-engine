<?php

namespace madong\workflow\tests\unit\expression;

use PHPUnit\Framework\TestCase;
use madong\workflow\expression\DefaultExpressionEngine;
use madong\workflow\context\ProcessVariables;

class DefaultExpressionEngineTest extends TestCase
{
    private DefaultExpressionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new DefaultExpressionEngine();
    }

    public function testEvaluateVariableSubstitution(): void
    {
        $vars = new ProcessVariables();
        $vars->set('amount', 500);
        $result = $this->engine->evaluate('${amount}', $vars);
        $this->assertEquals(500, $result);
    }

    public function testEvaluateToBool(): void
    {
        $vars = new ProcessVariables();
        $vars->set('amount', 500);
        $result = $this->engine->evaluateToBool('${amount} < 1000', $vars);
        $this->assertTrue($result);
    }

    public function testEvaluateToBoolFalse(): void
    {
        $vars = new ProcessVariables();
        $vars->set('amount', 1500);
        $result = $this->engine->evaluateToBool('${amount} < 1000', $vars);
        $this->assertFalse($result);
    }
}

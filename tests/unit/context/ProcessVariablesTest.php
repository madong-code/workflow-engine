<?php

namespace madong\workflow\tests\unit\context;

use PHPUnit\Framework\TestCase;
use madong\workflow\context\ProcessVariables;
use madong\workflow\libs\Dict;

class ProcessVariablesTest extends TestCase
{
    public function testGetSetGlobal(): void
    {
        $vars = new ProcessVariables();
        $vars->set('name', 'test');
        $this->assertEquals('test', $vars->get('name'));
    }

    public function testGetLocalOverridesGlobal(): void
    {
        $vars = new ProcessVariables();
        $vars->set('name', 'global');
        $vars->set('name', 'local', local: true);
        $this->assertEquals('local', $vars->get('name'));
    }

    public function testMergeGlobal(): void
    {
        $vars = new ProcessVariables();
        $vars->set('existing', 'value');
        $vars->mergeGlobal(['new' => 'data', 'existing' => 'overridden']);
        $this->assertEquals('overridden', $vars->get('existing'));
        $this->assertEquals('data', $vars->get('new'));
    }

    public function testHas(): void
    {
        $vars = new ProcessVariables();
        $vars->set('key', 'value');
        $this->assertTrue($vars->has('key'));
        $this->assertFalse($vars->has('missing'));
    }

    public function testClearLocal(): void
    {
        $vars = new ProcessVariables();
        $vars->set('global', 'g');
        $vars->set('local', 'l', local: true);
        $vars->clearLocal();
        $this->assertFalse($vars->has('local'));
        $this->assertTrue($vars->has('global'));
    }

    public function testToArray(): void
    {
        $vars = new ProcessVariables();
        $vars->set('a', 1);
        $vars->set('b', 2, local: true);
        $arr = $vars->toArray();
        $this->assertEquals(1, $arr['a']);
        $this->assertEquals(2, $arr['b']);
    }
}

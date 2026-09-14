<?php

namespace madong\workflow\tests\unit\model;

use PHPUnit\Framework\TestCase;
use madong\workflow\model\ProcessModel;
use madong\workflow\model\nodes\{StartNode, EndNode, TaskNode};

class ProcessModelTest extends TestCase
{
    public function testGetStartNode(): void
    {
        $model = new ProcessModel('test', '测试流程');
        $model->addNode(new StartNode('start', '开始'));
        $model->addNode(new TaskNode('task1', '任务1'));
        $model->addNode(new EndNode('end', '结束'));

        $startNode = $model->getStartNode();
        $this->assertNotNull($startNode);
        $this->assertEquals('start', $startNode->name);
    }

    public function testGetNode(): void
    {
        $model = new ProcessModel('test', '测试流程');
        $model->addNode(new TaskNode('task1', '任务1'));

        $node = $model->getNode('task1');
        $this->assertNotNull($node);
        $this->assertInstanceOf(TaskNode::class, $node);
    }

    public function testGetNodeNotFound(): void
    {
        $model = new ProcessModel('test', '测试流程');
        $node = $model->getNode('nonexistent');
        $this->assertNull($node);
    }
}

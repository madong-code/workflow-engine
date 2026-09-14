<?php

namespace madong\workflow\tests\unit\model\builder;

use PHPUnit\Framework\TestCase;
use madong\workflow\model\builder\ProcessModelBuilder;
use madong\workflow\model\builder\NodeBuilder;
use madong\workflow\model\builder\TaskNodeBuilder;
use madong\workflow\model\TransitionModel;
use madong\workflow\model\vo\Layout;
use madong\workflow\model\nodes\{StartNode, EndNode, TaskNode, ForkNode, JoinNode};
use madong\workflow\enum\PerformType;

class BuilderTest extends TestCase
{
    public function testProcessModelBuilderBuildsModel(): void
    {
        $builder = new ProcessModelBuilder();
        $model = $builder
            ->name('test_process')
            ->displayName('测试流程')
            ->type('test')
            ->build();

        $this->assertEquals('test_process', $model->name);
        $this->assertEquals('测试流程', $model->displayName);
        $this->assertEquals('test', $model->type);
    }

    public function testProcessModelBuilderCollectsTaskNodes(): void
    {
        $taskNode = (new TaskNodeBuilder())
            ->name('approve')
            ->displayName('审批')
            ->assignee('${manager}')
            ->build();

        $startNode = (new NodeBuilder())
            ->name('start')
            ->buildStartNode();

        $model = (new ProcessModelBuilder())
            ->name('test')
            ->addNode($startNode)
            ->addNode($taskNode)
            ->build();

        $this->assertCount(2, $model->getNodes());
        $this->assertCount(1, $model->getTasks());
        $this->assertInstanceOf(TaskNode::class, $model->getTasks()[0]);
        $this->assertEquals('approve', $model->getTasks()[0]->name);
    }

    public function testNodeBuilderBuildsStartNode(): void
    {
        $node = (new NodeBuilder())
            ->name('start')
            ->displayName('开始')
            ->buildStartNode();

        $this->assertInstanceOf(StartNode::class, $node);
        $this->assertEquals('start', $node->name);
        $this->assertEquals('开始', $node->displayName);
    }

    public function testNodeBuilderBuildsEndNode(): void
    {
        $node = (new NodeBuilder())
            ->name('end')
            ->displayName('结束')
            ->buildEndNode();

        $this->assertInstanceOf(EndNode::class, $node);
        $this->assertEquals('end', $node->name);
    }

    public function testNodeBuilderBuildsForkNode(): void
    {
        $node = (new NodeBuilder())
            ->name('fork')
            ->buildForkNode();

        $this->assertInstanceOf(ForkNode::class, $node);
    }

    public function testNodeBuilderBuildsJoinNode(): void
    {
        $node = (new NodeBuilder())
            ->name('join')
            ->buildJoinNode();

        $this->assertInstanceOf(JoinNode::class, $node);
    }

    public function testNodeBuilderWithLayout(): void
    {
        $layout = new Layout(100, 200, 300, 400);
        $node = (new NodeBuilder())
            ->name('start')
            ->layout($layout)
            ->buildStartNode();

        $this->assertEquals(100, $node->layout->x);
        $this->assertEquals(200, $node->layout->y);
    }

    public function testNodeBuilderWithTransitions(): void
    {
        $startNode = (new NodeBuilder())
            ->name('start')
            ->buildStartNode();

        $endNode = (new NodeBuilder())
            ->name('end')
            ->buildEndNode();

        $transition = new TransitionModel(
            name: 'e1',
            source: $startNode,
            target: $endNode,
        );

        $startWithOutput = (new NodeBuilder())
            ->name('start')
            ->addOutput($transition)
            ->buildStartNode();

        $this->assertCount(1, $startWithOutput->outputs);
        $this->assertSame($transition, $startWithOutput->outputs[0]);
    }

    public function testTaskNodeBuilderBuildsTaskNode(): void
    {
        $task = (new TaskNodeBuilder())
            ->name('approve')
            ->displayName('审批')
            ->assignee('${manager}')
            ->form('approve_form')
            ->performType(PerformType::NORMAL)
            ->build();

        $this->assertInstanceOf(TaskNode::class, $task);
        $this->assertEquals('approve', $task->name);
        $this->assertEquals('审批', $task->displayName);
        $this->assertEquals('${manager}', $task->assignee);
        $this->assertEquals('approve_form', $task->form);
        $this->assertEquals(PerformType::NORMAL, $task->performType);
    }

    public function testTaskNodeBuilderCopyFromNodeBuilder(): void
    {
        $nodeBuilder = (new NodeBuilder())
            ->name('task1')
            ->displayName('任务1');

        $taskBuilder = (new TaskNodeBuilder())
            ->copyFrom($nodeBuilder)
            ->assignee('admin');

        $task = $taskBuilder->build();

        $this->assertEquals('task1', $task->name);
        $this->assertEquals('任务1', $task->displayName);
        $this->assertEquals('admin', $task->assignee);
    }

    public function testBuildSimpleProcessModel(): void
    {
        $start = (new NodeBuilder())->name('start')->buildStartNode();
        $task  = (new TaskNodeBuilder())->name('apply')->assignee('user1')->build();
        $end   = (new NodeBuilder())->name('end')->buildEndNode();

        $t1 = new TransitionModel(name: 'e1', source: $start, target: $task);
        $t2 = new TransitionModel(name: 'e2', source: $task, target: $end);

        // 重新构建带 transitions 的节点
        $start = (new NodeBuilder())->name('start')->addOutput($t1)->buildStartNode();
        $task  = (new TaskNodeBuilder())->name('apply')->assignee('user1')->inputs([$t1])->outputs([$t2])->build();
        $end   = (new NodeBuilder())->name('end')->inputs([$t2])->buildEndNode();

        $model = (new ProcessModelBuilder())
            ->name('simple')
            ->displayName('简单流程')
            ->addNode($start)
            ->addNode($task)
            ->addNode($end)
            ->build();

        $this->assertCount(3, $model->getNodes());
        $this->assertCount(1, $model->getTasks());
        $this->assertNotNull($model->getStartNode());
        $this->assertEquals('start', $model->getStartNode()->name);
        $this->assertNotNull($model->getNode('apply'));
        $this->assertNull($model->getNode('nonexistent'));
    }
}

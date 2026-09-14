<?php

namespace madong\workflow\tests\unit\parser;

use PHPUnit\Framework\TestCase;
use madong\workflow\parser\{ModelParser, NodeParserRegistry};
use madong\workflow\parser\handler\{
    StartParser, EndParser, TaskParser, DecisionParser,
    ForkParser, JoinParser, CustomParser, SubProcessParser
};
use madong\workflow\model\nodes\{StartNode, EndNode, TaskNode};

class ModelParserTest extends TestCase
{
    private NodeParserRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new NodeParserRegistry();
        $this->registry->registerBatch([
            'start'    => StartParser::class,
            'end'      => EndParser::class,
            'task'     => TaskParser::class,
            'decision' => DecisionParser::class,
            'fork'     => ForkParser::class,
            'join'     => JoinParser::class,
            'custom'   => CustomParser::class,
            'wfSubProcess' => SubProcessParser::class,
        ]);
    }

    public function testParseSimpleLeave(): void
    {
        $json = file_get_contents(__DIR__ . '/../../fixture/data/simple_leave.json');
        $parser = new ModelParser($this->registry);
        $model = $parser->parse($json);

        $this->assertEquals('leave', $model->name);
        $this->assertEquals('请假流程', $model->displayName);
        $this->assertCount(4, $model->getNodes());

        $startNode = $model->getStartNode();
        $this->assertNotNull($startNode);
        $this->assertInstanceOf(StartNode::class, $startNode);
    }

    public function testParseWithTransitions(): void
    {
        $json = file_get_contents(__DIR__ . '/../../fixture/data/simple_leave.json');
        $parser = new ModelParser($this->registry);
        $model = $parser->parse($json);

        $startNode = $model->getStartNode();
        $this->assertNotEmpty($startNode->outputs);

        // start -> apply
        $transition = $startNode->outputs[0];
        $this->assertEquals('apply', $transition->to);
        $this->assertNotNull($transition->target);
        $this->assertInstanceOf(TaskNode::class, $transition->target);
    }
}

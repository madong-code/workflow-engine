<?php

namespace madong\workflow\tests\unit\container;

use PHPUnit\Framework\TestCase;
use madong\workflow\container\Container;

class ContainerTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $container = new Container();
        $container->set('test.service', new \stdClass());
        $service = $container->get('test.service');
        $this->assertInstanceOf(\stdClass::class, $service);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $container = new Container();
        $this->assertNull($container->get('nonexistent'));
    }

    public function testHas(): void
    {
        $container = new Container();
        $this->assertFalse($container->has('test.service'));
        $container->set('test.service', new \stdClass());
        $this->assertTrue($container->has('test.service'));
    }

    public function testSetBatch(): void
    {
        $container = new Container();
        $container->setBatch([
            'service.a' => new \stdClass(),
            'service.b' => new \stdClass(),
        ]);
        $this->assertTrue($container->has('service.a'));
        $this->assertTrue($container->has('service.b'));
    }
}

<?php

namespace madong\workflow\tests\unit\event;

use PHPUnit\Framework\TestCase;
use madong\workflow\event\{EventBus, ProcessEvent, ProcessEventListener};

class EventBusTest extends TestCase
{
    public function testPublishAndSubscribe(): void
    {
        $bus = new EventBus();
        $received = [];

        $bus->subscribe('test.event', new class($received) implements ProcessEventListener {
            public function __construct(private array &$log) {}
            public function onEvent(ProcessEvent $event): void {
                $this->log[] = $event->eventType;
            }
        });

        // Need to use reference properly
        $log = [];
        $listener = new class($log) implements ProcessEventListener {
            public array $log = [];
            public function onEvent(ProcessEvent $event): void {
                $this->log[] = $event->eventType;
            }
        };

        $bus = new EventBus();
        $bus->subscribe('test.event', $listener);
        $bus->publish('test.event');

        $this->assertCount(1, $listener->log);
        $this->assertEquals('test.event', $listener->log[0]);
    }

    public function testHasListeners(): void
    {
        $bus = new EventBus();
        $this->assertFalse($bus->hasListeners('test.event'));

        $bus->subscribe('test.event', new class implements ProcessEventListener {
            public function onEvent(ProcessEvent $event): void {}
        });

        $this->assertTrue($bus->hasListeners('test.event'));
    }
}

<?php

namespace madong\workflow\event;

class EventBus
{
    /** @var array<string, ProcessEventListener[]> */
    private array $listeners = [];

    public function subscribe(string $eventType, ProcessEventListener $listener): void
    {
        $this->listeners[$eventType][] = $listener;
    }

    public function unsubscribe(string $eventType, ProcessEventListener $listener): void
    {
        if (!isset($this->listeners[$eventType])) return;
        $this->listeners[$eventType] = array_filter(
            $this->listeners[$eventType],
            fn($l) => $l !== $listener
        );
    }

    public function publish(string $eventType, mixed $sourceId = null, mixed $data = null): void
    {
        $event = new ProcessEvent($eventType, $sourceId, $data);
        foreach ($this->listeners[$eventType] ?? [] as $listener) {
            $listener->onEvent($event);
        }
    }

    public function hasListeners(string $eventType): bool
    {
        return !empty($this->listeners[$eventType]);
    }

    public function getListeners(string $eventType): array
    {
        return $this->listeners[$eventType] ?? [];
    }
}

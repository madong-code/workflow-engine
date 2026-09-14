<?php

namespace madong\workflow\event;

class ProcessEvent
{
    public function __construct(
        public readonly string $eventType,
        public readonly mixed  $sourceId = null,
        public readonly mixed  $data = null,
        public readonly float  $timestamp = 0,
    ) {
        if ($this->timestamp === 0) {
            $this->timestamp = microtime(true);
        }
    }
}

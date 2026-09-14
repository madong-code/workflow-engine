<?php

namespace madong\workflow\scheduling;

use madong\workflow\event\{ProcessEvent, ProcessEventListener};
use madong\workflow\enum\EventType;

class SchedulerProcessEventListener implements ProcessEventListener
{
    public function onEvent(ProcessEvent $event): void
    {
        if ($event->eventType === EventType::TASK_START->value) {
            // 定时调度逻辑
        }
    }
}

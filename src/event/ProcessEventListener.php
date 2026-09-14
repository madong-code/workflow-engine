<?php

namespace madong\workflow\event;

interface ProcessEventListener
{
    public function onEvent(ProcessEvent $event): void;
}

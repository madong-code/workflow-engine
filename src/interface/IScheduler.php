<?php

namespace madong\workflow\interface;

interface IScheduler
{
    public function schedule(string $taskId, string $processInstanceId, array $params = []): void;
    public function cancel(string $taskId): void;
}

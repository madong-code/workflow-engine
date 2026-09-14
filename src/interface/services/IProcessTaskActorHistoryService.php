<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessTaskActor;

interface IProcessTaskActorHistoryService
{
    public function createHistoryTaskActor(IProcessTaskActor $actor): object;
    public function getByTaskId(string $taskId): array;
}

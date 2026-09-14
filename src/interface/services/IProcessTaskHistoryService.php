<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessTask;
use madong\workflow\interface\model\IProcessTaskHistory;

interface IProcessTaskHistoryService
{
    public function createHistoryProcessTask(IProcessTask $task): IProcessTaskHistory;
    public function getById(string $id): ?IProcessTaskHistory;
    public function getByProcessTaskId(string $taskId): ?IProcessTaskHistory;
    public function getByProcessInstanceId(string $instanceId): array;
}

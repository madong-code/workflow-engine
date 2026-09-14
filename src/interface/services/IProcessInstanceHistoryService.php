<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessInstance;
use madong\workflow\interface\model\IProcessInstanceHistory;

interface IProcessInstanceHistoryService
{
    public function createHistoryProcessInstance(IProcessInstance $instance): IProcessInstanceHistory;
    public function getById(string $id): ?IProcessInstanceHistory;
    public function getByProcessInstanceId(string $processInstanceId): array;
}

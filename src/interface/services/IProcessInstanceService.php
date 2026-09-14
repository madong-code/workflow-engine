<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessInstance;
use madong\workflow\interface\model\IProcessDefine;

interface IProcessInstanceService
{
    public function createProcessInstance(
        IProcessDefine $processDefine,
        string $operator,
        object $args,
        ?string $parentId = null,
        ?string $parentNodeName = null,
    ): IProcessInstance;

    public function getById(string $id): ?IProcessInstance;
    public function finishProcessInstance(string $id): void;
    public function rejectProcessInstance(string $id): void;
    public function suspendProcessInstance(string $id): void;
    public function activateProcessInstance(string $id): void;
    public function addVariable(string $id, array $vars): void;
    public function removeVariable(string $id, array $keys): void;
}

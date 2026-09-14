<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessCcInstance;

interface IProcessCcInstanceService
{
    public function createCcInstance(string $instanceId, string $actorId): IProcessCcInstance;
    public function getByInstanceId(string $instanceId): array;
}

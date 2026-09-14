<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessType;

interface IProcessTypeService
{
    public function createType(array $data): IProcessType;
    public function updateType(string $id, array $data): IProcessType;
    public function getById(string $id): ?IProcessType;
    public function getAll(): array;
}

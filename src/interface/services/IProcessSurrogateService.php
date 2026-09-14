<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessSurrogate;

interface IProcessSurrogateService
{
    public function createSurrogate(array $data): IProcessSurrogate;
    public function updateSurrogate(string $id, array $data): IProcessSurrogate;
    public function getById(string $id): ?IProcessSurrogate;
    public function getByOperator(string $operator): array;
}

<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessForm;

interface IProcessFormService
{
    public function createForm(array $data): IProcessForm;
    public function updateForm(string $id, array $data): IProcessForm;
    public function getById(string $id): ?IProcessForm;
    public function getByName(string $name): ?IProcessForm;
}

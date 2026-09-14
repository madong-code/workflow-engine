<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessDefine;
use madong\workflow\model\ProcessModel;

interface IProcessDefineService
{
    public function createProcessDefine(array $data): IProcessDefine;
    public function updateProcessDefine(string $id, array $data): IProcessDefine;
    public function getById(string $id): ?IProcessDefine;
    public function getProcessModel(string $defineId): ?ProcessModel;
    public function getProcessDefineByVersion(string $name, int $version): ?IProcessDefine;
    public function deleteProcessDefine(string $id): void;
    public function publishProcessDefine(string $id): IProcessDefine;
    public function disableProcessDefine(string $id): IProcessDefine;
}

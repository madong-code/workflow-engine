<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessDesign;

interface IProcessDesignService
{
    public function saveDesign(string $defineId, string $content): IProcessDesign;
    public function getById(string $id): ?IProcessDesign;
    public function getByDefineId(string $defineId): ?IProcessDesign;
}

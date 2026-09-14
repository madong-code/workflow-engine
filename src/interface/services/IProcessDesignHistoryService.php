<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessDesignHistory;

interface IProcessDesignHistoryService
{
    public function createHistory(string $designId, string $content): IProcessDesignHistory;
    public function getByDesignId(string $designId): array;
}

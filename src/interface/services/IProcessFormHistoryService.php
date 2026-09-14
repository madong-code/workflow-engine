<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessFormHistory;

interface IProcessFormHistoryService
{
    public function createHistory(string $formId, string $content): IProcessFormHistory;
    public function getByFormId(string $formId): array;
}

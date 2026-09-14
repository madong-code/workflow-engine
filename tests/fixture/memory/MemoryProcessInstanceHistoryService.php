<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\services\IProcessInstanceHistoryService;
use madong\workflow\interface\model\{IProcessInstance, IProcessInstanceHistory};

class MemoryProcessInstanceHistoryService implements IProcessInstanceHistoryService
{
    private array $histories = [];
    private int $autoId = 1;

    public function createHistoryProcessInstance(IProcessInstance $instance): IProcessInstanceHistory
    {
        $id = (string) $this->autoId++;
        $history = MemoryDict::of([
            'id'                  => $id,
            'process_instance_id' => $instance->getId(),
            'process_define_id'   => $instance->getProcessDefineId(),
            'state'               => $instance->getState(),
            'operator'            => $instance->getOperator(),
            'create_time'         => time(),
            'end_time'            => 0,
        ]);
        $this->histories[$id] = $history;
        return $history;
    }

    public function getById(string $id): ?IProcessInstanceHistory
    {
        return $this->histories[$id] ?? null;
    }

    public function getByProcessInstanceId(string $processInstanceId): array
    {
        return array_values(array_filter($this->histories, fn($h) =>
            $h->getData('process_instance_id') === $processInstanceId
        ));
    }
}

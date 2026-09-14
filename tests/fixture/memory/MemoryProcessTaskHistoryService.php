<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\services\IProcessTaskHistoryService;
use madong\workflow\interface\model\{IProcessTask, IProcessTaskHistory};

class MemoryProcessTaskHistoryService implements IProcessTaskHistoryService
{
    private array $histories = [];
    private int $autoId = 1;

    public function createHistoryProcessTask(IProcessTask $task): IProcessTaskHistory
    {
        $id = (string) $this->autoId++;
        $history = MemoryDict::of([
            'id'                  => $id,
            'process_task_id'     => $task->getId(),
            'process_instance_id' => $task->getProcessInstanceId(),
            'task_name'           => $task->getTaskName(),
            'task_state'          => $task->getTaskState(),
            'operator'            => $task->getOperator(),
            'create_time'         => time(),
            'end_time'            => 0,
        ]);
        $this->histories[$id] = $history;
        return $history;
    }

    public function getById(string $id): ?IProcessTaskHistory
    {
        return $this->histories[$id] ?? null;
    }

    public function getByProcessTaskId(string $taskId): ?IProcessTaskHistory
    {
        foreach ($this->histories as $h) {
            if ($h->getData('process_task_id') === $taskId) return $h;
        }
        return null;
    }

    public function getByProcessInstanceId(string $instanceId): array
    {
        return array_values(array_filter($this->histories, fn($h) =>
            $h->getData('process_instance_id') === $instanceId
        ));
    }
}

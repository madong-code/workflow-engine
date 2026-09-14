<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\services\IProcessTaskActorService;
use madong\workflow\interface\model\IProcessTaskActor;

class MemoryProcessTaskActorService implements IProcessTaskActorService
{
    private array $actors = [];
    private int $autoId = 1;

    public function createTaskActor(string $taskId, string $actorId, int $actorType): IProcessTaskActor
    {
        $id = (string) $this->autoId++;
        $actor = MemoryDict::of([
            'id'                 => $id,
            'process_task_id'    => $taskId,
            'actor_id'           => $actorId,
            'actor_type'         => $actorType,
        ]);
        $this->actors[$id] = $actor;
        return $actor;
    }

    public function getActorsByTaskId(string $taskId): array
    {
        return array_values(array_filter($this->actors, fn($a) =>
            $a->getData('process_task_id') === $taskId
        ));
    }

    public function removeActor(string $id): void
    {
        unset($this->actors[$id]);
    }
}

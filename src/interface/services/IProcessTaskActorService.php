<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessTaskActor;

interface IProcessTaskActorService
{
    public function createTaskActor(string $taskId, string $actorId, int $actorType): IProcessTaskActor;
    public function getActorsByTaskId(string $taskId): array;
    public function removeActor(string $id): void;
}

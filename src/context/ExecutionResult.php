<?php

namespace madong\workflow\context;

class ExecutionResult
{
    /** @var object[] 本次执行创建的所有任务 */
    private array $createdTasks = [];

    private bool $merged = false;

    private array $signals = [];

    public function addTask(object $task): void
    {
        $this->createdTasks[] = $task;
    }

    public function addTasks(array $tasks): void
    {
        array_push($this->createdTasks, ...$tasks);
    }

    public function getCreatedTasks(): array
    {
        return $this->createdTasks;
    }

    public function setMerged(bool $merged): void
    {
        $this->merged = $merged;
    }

    public function isMerged(): bool
    {
        return $this->merged;
    }

    public function setSignal(string $key, mixed $value = true): void
    {
        $this->signals[$key] = $value;
    }

    public function getSignal(string $key, mixed $default = null): mixed
    {
        return $this->signals[$key] ?? $default;
    }

    public function hasSignal(string $key): bool
    {
        return isset($this->signals[$key]);
    }
}

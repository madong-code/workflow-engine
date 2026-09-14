<?php

namespace madong\workflow\context;

use madong\workflow\Engine;
use madong\workflow\interface\model\IProcessInstance;
use madong\workflow\interface\model\IProcessTask;
use madong\workflow\model\ProcessModel;

class ExecutionContext
{
    public function __construct(
        public readonly string            $processInstanceId,
        public readonly string|int        $processTaskId,
        public readonly string            $operator,
        public readonly Engine            $engine,
        public readonly ?IProcessInstance $processInstance = null,
        public readonly ?IProcessTask     $processTask = null,
        public readonly ?ProcessModel     $processModel = null,
        public readonly ProcessVariables  $variables = new ProcessVariables(),
        public readonly ExecutionResult   $result = new ExecutionResult(),
    ) {}

    public function withProcessModel(ProcessModel $model): self
    {
        return new self(
            $this->processInstanceId, $this->processTaskId, $this->operator,
            $this->engine, $this->processInstance, $this->processTask,
            $model, $this->variables, $this->result,
        );
    }

    public function withProcessTask(IProcessTask $task): self
    {
        return new self(
            $this->processInstanceId, $this->processTaskId, $this->operator,
            $this->engine, $this->processInstance, $task,
            $this->processModel, $this->variables, $this->result,
        );
    }

    public function withProcessInstance(IProcessInstance $instance): self
    {
        return new self(
            $this->processInstanceId, $this->processTaskId, $this->operator,
            $this->engine, $instance, $this->processTask,
            $this->processModel, $this->variables, $this->result,
        );
    }

    public function getVariable(string $key, mixed $default = null): mixed
    {
        return $this->variables->get($key, $default);
    }

    public function setVariable(string $key, mixed $value, bool $local = false): void
    {
        $this->variables->set($key, $value, $local);
    }

    public function addTasks(array $tasks): void
    {
        $this->result->addTasks($tasks);
    }

    public function getCreatedTasks(): array
    {
        return $this->result->getCreatedTasks();
    }
}

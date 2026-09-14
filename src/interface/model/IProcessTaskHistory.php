<?php

namespace madong\workflow\interface\model;

interface IProcessTaskHistory
{
    public function getId(): string;
    public function getProcessTaskId(): string;
    public function getProcessInstanceId(): string;
    public function getTaskName(): string;
    public function getTaskState(): string;
    public function getOperator(): ?string;
    public function getCreateTime(): int;
    public function getEndTime(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

<?php

namespace madong\workflow\interface\model;

interface IProcessTask
{
    public function getId(): string;
    public function getProcessInstanceId(): string;
    public function getTaskName(): string;
    public function getTaskState(): string;
    public function getTaskType(): string;
    public function getPerformType(): string;
    public function getOperator(): ?string;
    public function getCreateTime(): int;

    /** 兜底: 访问非标化字段 */
    public function getData(string $key, mixed $default = null): mixed;
}

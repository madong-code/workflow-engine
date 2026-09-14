<?php

namespace madong\workflow\interface\model;

interface IProcessInstance
{
    public function getId(): string;
    public function getProcessDefineId(): string;
    public function getState(): string;
    public function getOperator(): string;
    public function getParentId(): ?string;
    public function getParentNodeName(): ?string;
    public function getVariable(): array;
    public function getCreateTime(): int;

    /** 兜底: 访问非标化字段 */
    public function getData(string $key, mixed $default = null): mixed;
}

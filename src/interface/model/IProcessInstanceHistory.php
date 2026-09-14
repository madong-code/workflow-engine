<?php

namespace madong\workflow\interface\model;

interface IProcessInstanceHistory
{
    public function getId(): string;
    public function getProcessInstanceId(): string;
    public function getProcessDefineId(): string;
    public function getState(): string;
    public function getOperator(): string;
    public function getCreateTime(): int;
    public function getEndTime(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

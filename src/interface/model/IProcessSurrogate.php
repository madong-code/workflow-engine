<?php

namespace madong\workflow\interface\model;

interface IProcessSurrogate
{
    public function getId(): string;
    public function getSurrogate(): string;
    public function getOperator(): string;
    public function getProcessName(): string;

    public function getData(string $key, mixed $default = null): mixed;
}

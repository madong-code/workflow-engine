<?php

namespace madong\workflow\interface\model;

interface IProcessCcInstance
{
    public function getId(): string;
    public function getProcessInstanceId(): string;
    public function getActorId(): string;

    public function getData(string $key, mixed $default = null): mixed;
}

<?php

namespace madong\workflow\interface\model;

interface IProcessTaskActor
{
    public function getId(): string;
    public function getProcessTaskId(): string;
    public function getProcessInstanceId(): string;
    public function getActorId(): string;
    public function getActorType(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

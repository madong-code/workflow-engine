<?php

namespace madong\workflow\interface\model;

interface IProcessTaskActorHistory
{
    public function getId(): string;
    public function getProcessTaskId(): string;
    public function getActorId(): string;
    public function getActorType(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

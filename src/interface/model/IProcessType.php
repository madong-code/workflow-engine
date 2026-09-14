<?php

namespace madong\workflow\interface\model;

interface IProcessType
{
    public function getId(): string;
    public function getName(): string;

    public function getData(string $key, mixed $default = null): mixed;
}

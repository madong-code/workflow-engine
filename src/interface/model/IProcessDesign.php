<?php

namespace madong\workflow\interface\model;

interface IProcessDesign
{
    public function getId(): string;
    public function getProcessDefineId(): string;
    public function getContent(): ?string;
    public function getCreateTime(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

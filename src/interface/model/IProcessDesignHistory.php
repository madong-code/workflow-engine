<?php

namespace madong\workflow\interface\model;

interface IProcessDesignHistory
{
    public function getId(): string;
    public function getProcessDesignId(): string;
    public function getContent(): ?string;
    public function getCreateTime(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

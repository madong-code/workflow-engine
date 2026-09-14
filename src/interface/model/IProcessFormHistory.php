<?php

namespace madong\workflow\interface\model;

interface IProcessFormHistory
{
    public function getId(): string;
    public function getProcessFormId(): string;
    public function getContent(): ?string;
    public function getCreateTime(): int;

    public function getData(string $key, mixed $default = null): mixed;
}

<?php

namespace madong\workflow\interface\model;

interface IProcessDefine
{
    public function getId(): string;
    public function getName(): string;
    public function getDisplayName(): string;
    public function getState(): string;
    public function getVersion(): int;
    public function getContent(): ?string;

    public function getData(string $key, mixed $default = null): mixed;
}

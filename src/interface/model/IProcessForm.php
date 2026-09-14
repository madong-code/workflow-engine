<?php

namespace madong\workflow\interface\model;

interface IProcessForm
{
    public function getId(): string;
    public function getName(): string;
    public function getDisplayName(): string;
    public function getContent(): ?string;

    public function getData(string $key, mixed $default = null): mixed;
}

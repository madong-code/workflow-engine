<?php

namespace madong\workflow\interface\model;

interface IProcessDefineFavorite
{
    public function getId(): string;
    public function getProcessDefineId(): string;
    public function getUserId(): string;

    public function getData(string $key, mixed $default = null): mixed;
}

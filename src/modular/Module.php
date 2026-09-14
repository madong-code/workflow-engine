<?php

namespace madong\workflow\modular;

use madong\workflow\container\Container;

interface Module
{
    public function id(): string;
    public function depends(): array;
    public function register(Container $container): void;
    public function boot(): void;
    public function shutdown(): void;
}

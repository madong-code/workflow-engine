<?php

namespace madong\workflow\container;

interface ContainerInterface
{
    public function get(string $name): ?object;
    
    public function set(string $name, string|object $service): void;
    
    public function has(string $name): bool;
    
    public function getExtension(string $key, mixed $default = null): mixed;
}

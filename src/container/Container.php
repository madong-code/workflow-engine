<?php

namespace madong\workflow\container;

use DI\ContainerBuilder;
use madong\workflow\exception\FlowException;

class Container implements ContainerInterface
{
    private \DI\Container $diContainer;
    private array $definitions = [];
    private array $instances = [];
    private array $extensions = [];

    public function __construct(array $definitions = [], array $extensions = [])
    {
        $this->extensions = $extensions;
        $this->definitions = $definitions;

        // 将 [interface => className] 格式转换为 PHP-DI 定义格式
        $diDefinitions = [];
        foreach ($definitions as $name => $service) {
            $diDefinitions[$name] = is_string($service) ? \DI\create($service) : $service;
        }

        $builder = new ContainerBuilder();
        if (!empty($diDefinitions)) {
            $builder->addDefinitions($diDefinitions);
        }
        $builder->useAutowiring(true);
        $this->diContainer = $builder->build();
    }

    public function set(string $name, string|object $service): void
    {
        $this->definitions[$name] = $service;
        if (is_string($service)) {
            $this->diContainer->set($name, \DI\create($service));
        } else {
            $this->diContainer->set($name, $service);
        }
        unset($this->instances[$name]);
    }

    public function setBatch(array $services): void
    {
        foreach ($services as $name => $service) {
            $this->set($name, $service);
        }
    }

    public function get(string $name): ?object
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        try {
            if ($this->diContainer->has($name)) {
                $instance = $this->diContainer->get($name);
                $this->instances[$name] = $instance;
                return $instance;
            }
        } catch (\Throwable) {}

        return null;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]) || $this->diContainer->has($name);
    }

    public function getExtension(string $key, mixed $default = null): mixed
    {
        return $this->extensions[$key] ?? $default;
    }

    public function getDefinitions(): array
    {
        return $this->definitions;
    }
}

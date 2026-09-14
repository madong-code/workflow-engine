<?php

namespace madong\workflow\container;

/**
 * 外部容器适配器
 * 用于适配webman等框架的容器，使其符合workflow的ContainerInterface接口
 */
class ExternalContainerAdapter implements ContainerInterface
{
    private object $externalContainer;
    private array $extensions = [];

    public function __construct(object $externalContainer, array $extensions = [])
    {
        $this->externalContainer = $externalContainer;
        $this->extensions = $extensions;
    }

    public function get(string $name): ?object
    {
        try {
            if (method_exists($this->externalContainer, 'get')) {
                return $this->externalContainer->get($name);
            } elseif (method_exists($this->externalContainer, 'make')) {
                return $this->externalContainer->make($name);
            }
        } catch (\Throwable) {}
        return null;
    }

    public function set(string $name, string|object $service): void
    {
        if (method_exists($this->externalContainer, 'set')) {
            if (is_string($service)) {
                $this->externalContainer->set($name, \DI\create($service));
            } else {
                $this->externalContainer->set($name, $service);
            }
        } elseif (method_exists($this->externalContainer, 'bind')) {
            $this->externalContainer->bind($name, $service);
        }
    }

    public function has(string $name): bool
    {
        if (method_exists($this->externalContainer, 'has')) {
            return $this->externalContainer->has($name);
        }
        return false;
    }

    public function getExtension(string $key, mixed $default = null): mixed
    {
        return $this->extensions[$key] ?? $default;
    }

    public function setBatch(array $services): void
    {
        foreach ($services as $name => $service) {
            $this->set($name, $service);
        }
    }
}

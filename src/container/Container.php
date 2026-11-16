<?php

namespace madong\workflow\container;

use DI\ContainerBuilder;
use madong\workflow\exception\FlowException;

class Container implements ContainerInterface
{
    /** php-di 可用时为 DI\Container；缺失时为 null（降级为数组容器） */
    private ?\DI\Container $diContainer;
    private array $definitions = [];
    private array $instances = [];
    private array $extensions = [];

    public function __construct(array $definitions = [], array $extensions = [])
    {
        $this->extensions = $extensions;
        $this->definitions = $definitions;

        if (!class_exists(ContainerBuilder::class)) {
            // 无 composer 安装场景（如纯 PSR-4 引入的 demo）：降级为数组容器，
            // 构造传入的实例服务直接缓存；类名字符串惰性实例化（见 get()）
            $this->diContainer = null;
            foreach ($this->definitions as $name => $service) {
                if (is_object($service)) {
                    $this->instances[$name] = $service;
                }
            }
            return;
        }

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
        if ($this->diContainer === null) {
            // 降级容器：实例服务直接缓存，保证 get() 命中
            if (is_object($service)) {
                $this->instances[$name] = $service;
            }
            return;
        }
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

        // 降级容器：实例服务已在 set() 后由 get() 惰性缓存；字符串类名无参实例化
        if ($this->diContainer === null) {
            if (isset($this->definitions[$name]) && is_string($this->definitions[$name]) && class_exists($this->definitions[$name])) {
                return $this->instances[$name] = new $this->definitions[$name]();
            }
            return null;
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
        if ($this->diContainer === null) {
            return isset($this->definitions[$name]);
        }
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

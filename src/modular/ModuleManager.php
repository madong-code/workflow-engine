<?php

namespace madong\workflow\modular;

use madong\workflow\container\Container;
use madong\workflow\exception\FlowException;

class ModuleManager
{
    /** @var array<string, Module> */
    private array $modules = [];
    private Container $container;
    private bool $booted = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function install(Module $module): void
    {
        $id = $module->id();
        if (isset($this->modules[$id])) {
            throw new FlowException("模块 [{$id}] 已安装");
        }
        foreach ($module->depends() as $depId) {
            if (!isset($this->modules[$depId])) {
                throw new FlowException("模块 [{$id}] 依赖 [{$depId}] 未安装");
            }
        }
        $this->modules[$id] = $module;
        $module->register($this->container);
    }

    public function bootAll(): void
    {
        if ($this->booted) return;
        foreach ($this->topologicalSort() as $module) {
            $module->boot();
        }
        $this->booted = true;
    }

    public function uninstall(string $id): void
    {
        if (!isset($this->modules[$id])) return;
        foreach ($this->modules as $m) {
            if (in_array($id, $m->depends())) {
                throw new FlowException("模块 [{$m->id()}] 依赖 [{$id}]，无法卸载");
            }
        }
        $this->modules[$id]->shutdown();
        unset($this->modules[$id]);
    }

    private function topologicalSort(): array
    {
        $sorted = [];
        $visited = [];

        $visit = function (string $id) use (&$visit, &$sorted, &$visited) {
            if (isset($visited[$id])) return;
            $visited[$id] = true;
            foreach ($this->modules[$id]->depends() as $depId) {
                $visit($depId);
            }
            $sorted[] = $this->modules[$id];
        };

        foreach (array_keys($this->modules) as $id) {
            $visit($id);
        }
        return $sorted;
    }

    public function getInstalledModules(): array
    {
        return array_keys($this->modules);
    }

    public function isInstalled(string $id): bool
    {
        return isset($this->modules[$id]);
    }
}

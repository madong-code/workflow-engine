<?php

namespace madong\workflow\parser;

use madong\workflow\exception\FlowException;

class NodeParserRegistry
{
    /** @var array<string, class-string<NodeParser>> */
    private array $parsers = [];

    /** @var array<string, NodeParser> */
    private array $instances = [];

    public function register(string $nodeType, string $parserClass, bool $override = false): void
    {
        if (!$override && isset($this->parsers[$nodeType])) {
            throw new FlowException("解析器 [{$nodeType}] 已注册，使用 override=true 覆盖");
        }
        $this->parsers[$nodeType] = $parserClass;
        unset($this->instances[$nodeType]);
    }

    public function registerBatch(array $parsers, bool $override = false): void
    {
        foreach ($parsers as $type => $class) {
            $this->register($type, $class, $override);
        }
    }

    public function find(string $nodeType): ?NodeParser
    {
        if (isset($this->instances[$nodeType])) {
            return $this->instances[$nodeType];
        }
        $class = $this->parsers[$nodeType] ?? null;
        if ($class === null) return null;

        $instance = new $class();
        $this->instances[$nodeType] = $instance;
        return $instance;
    }

    public function has(string $nodeType): bool
    {
        return isset($this->parsers[$nodeType]);
    }

    public function registeredTypes(): array
    {
        return array_keys($this->parsers);
    }

    public function unregister(string $nodeType): void
    {
        unset($this->parsers[$nodeType], $this->instances[$nodeType]);
    }
}

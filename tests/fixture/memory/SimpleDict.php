<?php

namespace madong\workflow\tests\fixture\memory;

class SimpleDict implements \ArrayAccess, \IteratorAggregate, \Countable
{
    protected array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function of(array $data): self
    {
        return new self($data);
    }

    public function put(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function containsKey(string $key): bool
    {
        return $this->has($key);
    }

    public function remove(string $key): self
    {
        unset($this->data[$key]);
        return $this;
    }

    public function clear(): self
    {
        $this->data = [];
        return $this;
    }

    public function getAll(): array
    {
        return $this->data;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->put($offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            $this->remove($offset);
        }
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    // 实现接口常用方法
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function getId(): string
    {
        return $this->get('id', '');
    }

    public function getName(): string
    {
        return $this->get('name', '');
    }

    public function getDisplayName(): string
    {
        return $this->get('displayName', $this->get('display_name', ''));
    }

    public function getState(): string
    {
        return $this->get('state', '');
    }

    public function getVersion(): int
    {
        return $this->get('version', 1);
    }

    public function getContent(): ?string
    {
        return $this->get('content');
    }

    public function getProcessDefineId(): string
    {
        return $this->get('process_define_id', '');
    }

    public function getProcessInstanceId(): string
    {
        return $this->get('process_instance_id', '');
    }

    public function getTaskName(): string
    {
        return $this->get('task_name', '');
    }

    public function getOperator(): string
    {
        return $this->get('operator', '');
    }

    public function getVariable(): array
    {
        $var = $this->get('variable', '{}');
        return is_string($var) ? json_decode($var, true) ?: [] : (array)$var;
    }

    public function getParentId(): ?string
    {
        return $this->get('parent_id');
    }

    public function getParentNodeName(): ?string
    {
        return $this->get('parent_node_name');
    }
}

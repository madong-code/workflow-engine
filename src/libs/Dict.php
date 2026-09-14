<?php

namespace madong\workflow\libs;

class Dict implements \ArrayAccess, \IteratorAggregate, \Countable
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
}

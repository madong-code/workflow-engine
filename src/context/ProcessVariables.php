<?php

namespace madong\workflow\context;

use madong\workflow\libs\Dict;

class ProcessVariables
{
    private Dict $global;
    private Dict $local;

    public function __construct(?Dict $initial = null)
    {
        $this->global = $initial ?? Dict::of([]);
        $this->local  = Dict::of([]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->local->containsKey($key)) {
            return $this->local->get($key, $default);
        }
        return $this->global->get($key, $default);
    }

    public function set(string $key, mixed $value, bool $local = false): void
    {
        if ($local) {
            $this->local->put($key, $value);
        } else {
            $this->global->put($key, $value);
        }
    }

    public function mergeGlobal(array|Dict $overlay): void
    {
        if ($overlay instanceof Dict) {
            $overlay = $overlay->getAll();
        }
        foreach ($overlay as $k => $v) {
            $this->global->put($k, $v);
        }
    }

    public function clearLocal(): void
    {
        $this->local->clear();
    }

    public function getGlobal(): Dict
    {
        return $this->global;
    }

    public function getLocal(): Dict
    {
        return $this->local;
    }

    public function has(string $key): bool
    {
        return $this->local->containsKey($key) || $this->global->containsKey($key);
    }

    public function toArray(): array
    {
        $result = $this->global->getAll();
        foreach ($this->local->getAll() as $k => $v) {
            $result[$k] = $v;
        }
        return $result;
    }

    public function getAll(): array
    {
        return $this->toArray();
    }
}

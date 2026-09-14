<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\services\IProcessInstanceService;
use madong\workflow\interface\model\{IProcessInstance, IProcessDefine};
use madong\workflow\enum\ProcessState;

class MemoryProcessInstanceService implements IProcessInstanceService
{
    private array $instances = [];
    private int $autoId = 1;

    public function createProcessInstance(
        IProcessDefine $processDefine,
        string $operator,
        object $args,
        ?string $parentId = null,
        ?string $parentNodeName = null,
    ): IProcessInstance {
        $id = (string) $this->autoId++;
        $instance = MemoryDict::of([
            'id'                 => $id,
            'process_define_id'  => $processDefine->getId(),
            'state'              => ProcessState::ACTIVE->value,
            'operator'           => $operator,
            'variable'           => json_encode(method_exists($args, 'toArray') ? $args->toArray() : (method_exists($args, 'getAll') ? (array)$args->getAll() : [])),
            'parent_id'          => $parentId,
            'parent_node_name'   => $parentNodeName,
            'create_time'        => time(),
        ]);
        $this->instances[$id] = $instance;
        return $instance;
    }

    public function getById(string $id): ?IProcessInstance
    {
        return $this->instances[$id] ?? null;
    }

    public function finishProcessInstance(string $id): void
    {
        if (isset($this->instances[$id])) {
            $this->instances[$id]->put('state', ProcessState::FINISHED->value);
        }
    }

    public function rejectProcessInstance(string $id): void
    {
        if (isset($this->instances[$id])) {
            $this->instances[$id]->put('state', ProcessState::REJECTED->value);
        }
    }

    public function suspendProcessInstance(string $id): void
    {
        if (isset($this->instances[$id])) {
            $this->instances[$id]->put('state', ProcessState::SUSPENDED->value);
        }
    }

    public function activateProcessInstance(string $id): void
    {
        if (isset($this->instances[$id])) {
            $this->instances[$id]->put('state', ProcessState::ACTIVE->value);
        }
    }

    public function addVariable(string $id, array $vars): void
    {
        if (!isset($this->instances[$id])) return;
        $existing = json_decode($this->instances[$id]->getData('variable', '{}'), true) ?: [];
        $merged = array_merge($existing, $vars);
        $this->instances[$id]->put('variable', json_encode($merged));
    }

    public function removeVariable(string $id, array $keys): void
    {
        if (!isset($this->instances[$id])) return;
        $existing = json_decode($this->instances[$id]->getData('variable', '{}'), true) ?: [];
        foreach ($keys as $k) unset($existing[$k]);
        $this->instances[$id]->put('variable', json_encode($existing));
    }
}

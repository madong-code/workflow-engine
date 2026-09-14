<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\model\{
    IProcessInstance, IProcessTask, IProcessDefine, IProcessTaskActor,
    IProcessInstanceHistory, IProcessTaskHistory
};
use madong\workflow\enum\TaskState;

/**
 * 内存版 Dict — 扩展 SimpleDict 添加 getData() 及接口兼容方法
 * 用于 Memory 服务层返回的对象同时满足 Dict 操作和接口类型约束
 */
class MemoryDict extends SimpleDict implements
    IProcessInstance,
    IProcessTask,
    IProcessDefine,
    IProcessTaskActor,
    IProcessInstanceHistory,
    IProcessTaskHistory
{
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    // --- 所有接口的公共方法 ---

    public function getId(): string { return (string)$this->get('id', ''); }
    public function getProcessInstanceId(): string { return (string)$this->get('process_instance_id', ''); }
    public function getState(): string { return (string)$this->get('state', ''); }
    public function getCreateTime(): int { return (int)$this->get('create_time', 0); }
    public function getEndTime(): int { return (int)$this->get('end_time', 0); }

    // --- IProcessTask & IProcessTaskHistory 特定方法 ---

    public function getTaskName(): string { return (string)$this->get('task_name', ''); }
    public function getTaskState(): string { return (string)$this->get('task_state', ''); }
    public function getTaskType(): string { return (string)$this->get('task_type', ''); }
    public function getPerformType(): string { return (string)$this->get('perform_type', ''); }
    public function getProcessTaskId(): string { return (string)$this->get('process_task_id', ''); }

    // --- IProcessInstance 特定方法 ---

    public function getProcessDefineId(): string { return (string)$this->get('process_define_id', ''); }
    public function getParentId(): ?string { return $this->get('parent_id'); }
    public function getParentNodeName(): ?string { return $this->get('parent_node_name'); }
    public function getVariable(): array
    {
        $v = $this->get('variable', '{}');
        if (is_string($v)) {
            return json_decode($v, true) ?: [];
        }
        return (array)$v;
    }

    // --- IProcessDefine 特定方法 ---

    public function getName(): string { return (string)$this->get('name', ''); }
    public function getDisplayName(): string { return (string)$this->get('display_name', ''); }
    public function getContent(): ?string { return $this->get('content'); }
    public function getVersion(): int { return (int)$this->get('version', 0); }

    // --- IProcessTaskActor 特定方法 ---

    public function getActorId(): string { return (string)$this->get('actor_id', ''); }
    public function getActorType(): int { return (int)$this->get('actor_type', 0); }

    // --- getOperator() — 兼容所有接口：对于要求 ?string 的接口，我们的 string 是协变兼容的 ---

    public function getOperator(): string { return (string)$this->get('operator', ''); }

    // --- 工厂方法 ---

    public static function of(array $entries): self
    {
        $dict = new self();
        foreach ($entries as $key => $value) {
            $dict->put($key, $value);
        }
        return $dict;
    }
}

<?php
/**
 * workflow demo · 数据库行 Dict 实体
 *
 * 把 PDO 查询出的关联数组行包装为引擎 I* 模型接口的实现（对齐引擎
 * tests/fixture/memory/MemoryDict 的做法），使 PDO 存储层无需引入任何 ORM。
 */

declare(strict_types=1);

namespace madong\workflow\demo;

use madong\workflow\interface\model\{
    IProcessDefine,
    IProcessInstance,
    IProcessTask,
    IProcessTaskActor,
    IProcessInstanceHistory,
    IProcessTaskHistory
};

final class RowDict implements
    IProcessDefine,
    IProcessInstance,
    IProcessTask,
    IProcessTaskActor,
    IProcessInstanceHistory,
    IProcessTaskHistory
{
    private array $data;

    public function __construct(array $row)
    {
        $this->data = $row;
    }

    public static function of(array $row): self
    {
        return new self($row);
    }

    /** 兜底: 访问非标准化字段 */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function getAll(): array
    {
        return $this->data;
    }

    // --- 通用字段 ---

    public function getId(): string { return (string) ($this->data['id'] ?? ''); }
    public function getState(): string { return (string) ($this->data['state'] ?? ''); }
    public function getCreateTime(): int { return (int) ($this->data['create_time'] ?? 0); }
    public function getEndTime(): int { return (int) ($this->data['end_time'] ?? 0); }
    public function getOperator(): string { return (string) ($this->data['operator'] ?? ''); }

    // --- IProcessDefine ---

    public function getName(): string { return (string) ($this->data['name'] ?? ''); }
    public function getDisplayName(): string { return (string) ($this->data['display_name'] ?? ''); }
    public function getContent(): ?string { return isset($this->data['content']) ? (string) $this->data['content'] : null; }
    public function getVersion(): int { return (int) ($this->data['version'] ?? 1); }

    // --- IProcessInstance ---

    public function getProcessDefineId(): string { return (string) ($this->data['process_define_id'] ?? ''); }
    public function getParentId(): ?string { return isset($this->data['parent_id']) ? (string) $this->data['parent_id'] : null; }
    public function getParentNodeName(): ?string { return isset($this->data['parent_node_name']) ? (string) $this->data['parent_node_name'] : null; }

    public function getVariable(): array
    {
        $v = $this->data['variable'] ?? '{}';
        return is_string($v) ? (json_decode($v, true) ?: []) : (array) $v;
    }

    // --- IProcessTask ---

    public function getTaskName(): string { return (string) ($this->data['task_name'] ?? ''); }
    public function getTaskState(): string { return (string) ($this->data['task_state'] ?? ''); }
    public function getTaskType(): string { return (string) ($this->data['task_type'] ?? ''); }
    public function getPerformType(): string { return (string) ($this->data['perform_type'] ?? ''); }
    public function getProcessInstanceId(): string { return (string) ($this->data['process_instance_id'] ?? ''); }

    // --- IProcessTaskActor ---

    public function getProcessTaskId(): string { return (string) ($this->data['process_task_id'] ?? ''); }
    public function getActorId(): string { return (string) ($this->data['actor_id'] ?? ''); }
    public function getActorType(): int { return (int) ($this->data['actor_type'] ?? 0); }
}

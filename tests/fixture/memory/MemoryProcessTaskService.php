<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\services\IProcessTaskService;
use madong\workflow\interface\model\IProcessTask;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\context\ExecutionContext;
use madong\workflow\enum\{TaskState, TaskType, PerformType, ProcessConst};
class MemoryProcessTaskService implements IProcessTaskService
{
    private array $tasks = [];
    private int $autoId = 1;

    public function createTask(TaskNode $taskNode, ExecutionContext $ctx): array
    {
        $id = (string) $this->autoId++;
        $operator = $this->resolveAssignee($taskNode->assignee, $ctx);
        $task = MemoryDict::of([
            'id'                  => $id,
            'process_instance_id' => $ctx->processInstanceId,
            'task_name'           => $taskNode->name,
            'task_state'          => TaskState::DOING->value,
            'task_type'           => $taskNode->taskType?->value ?? TaskType::MAJOR->value,
            'perform_type'        => $taskNode->performType?->value ?? PerformType::NORMAL->value,
            'operator'            => $operator,
            'create_time'         => time(),
        ]);
        $this->tasks[$id] = $task;
        return [$task];
    }

    public function createCountersignTask(TaskNode $taskNode, ExecutionContext $ctx): array
    {
        $operators = $this->resolveAssignees($taskNode->assignee, $ctx);
        if (empty($operators) || !is_array($operators)) {
            // 无操作人列表时退化为单实例
            return $this->createTask($taskNode, $ctx);
        }

        // 记录会签操作人列表，供合并判断使用
        $prefix = ProcessConst::COUNTERSIGN_PREFIX . $taskNode->name . "_";
        $ctx->setVariable($prefix . ProcessConst::OPERATOR_LIST, array_values($operators));
        $ctx->setVariable($prefix . ProcessConst::NR_OF_COMPLETED, 0);

        $tasks = [];
        foreach ($operators as $operator) {
            $id = (string) $this->autoId++;
            $task = MemoryDict::of([
                'id'                  => $id,
                'process_instance_id' => $ctx->processInstanceId,
                'task_name'           => $taskNode->name,
                'task_state'          => TaskState::DOING->value,
                'task_type'           => $taskNode->taskType?->value ?? TaskType::MAJOR->value,
                'perform_type'        => $taskNode->performType?->value ?? PerformType::COUNTERSIGN->value,
                'operator'            => (string) $operator,
                'create_time'         => time(),
            ]);
            $this->tasks[$id] = $task;
            $tasks[] = $task;
        }
        return $tasks;
    }

    /**
     * 解析 assignee 为单个操作人：
     * - ${var} / node.var 形式从流程变量取值（值可为标量或单元素数组）
     * - 否则原样返回字符串
     */
    private function resolveAssignee(?string $assignee, ExecutionContext $ctx): ?string
    {
        $key = $this->extractVariableKey($assignee);
        if ($key === null) return $assignee;

        $value = $ctx->getVariable($key);
        if (is_array($value)) {
            return count($value) > 0 ? (string) reset($value) : $assignee;
        }
        if ($value !== null && !is_object($value)) {
            return (string) $value;
        }
        return $assignee;
    }

    /**
     * 从 assignee 中提取变量名：
     * - ${var} → var
     * - node.var → var（取点号后段）
     * - 非表达式形式 → null
     */
    private function extractVariableKey(?string $assignee): ?string
    {
        if ($assignee === null || $assignee === '') return null;
        if (preg_match('/^\$\{(\w+)\}$/', $assignee, $m)) {
            return $m[1];
        }
        if (str_contains($assignee, '.')) {
            return substr($assignee, strrpos($assignee, '.') + 1);
        }
        return null;
    }

    /**
     * 解析 assignee 为操作人数组（会签用）：
     * - 变量值为数组时直接返回
     * - 标量时包装为单元素数组
     * - 解析失败返回空数组
     */
    private function resolveAssignees(?string $assignee, ExecutionContext $ctx): array
    {
        $key = $this->extractVariableKey($assignee);
        if ($key === null) return [];

        $value = $ctx->getVariable($key);
        if (is_array($value)) {
            return array_values(array_filter($value, fn($v) => $v !== null && $v !== ''));
        }
        if ($value !== null && !is_object($value)) {
            return [(string) $value];
        }
        return [];
    }

    public function getById(string $id): ?IProcessTask
    {
        return $this->tasks[$id] ?? null;
    }

    public function finishProcessTask(string $id, string $operator, object $args): void
    {
        if (isset($this->tasks[$id])) {
            $this->tasks[$id]->put('task_state', TaskState::FINISHED->value);
        }
    }

    public function withdrawTask(string $id, string $operator): void
    {
        if (isset($this->tasks[$id])) {
            $this->tasks[$id]->put('task_state', TaskState::CANCELED->value);
        }
    }

    public function isAllowed(IProcessTask $task, string $operator): bool
    {
        return true; // 测试环境宽松
    }

    public function getDoingTaskList(string $instanceId, string $taskNames): array
    {
        return array_values(array_filter($this->tasks, fn($t) =>
            $t->getData('process_instance_id') === $instanceId &&
            $t->getData('task_state') === TaskState::DOING->value
        ));
    }

    public function getTaskListByOperator(string $operator, array $filters = []): array
    {
        return array_values(array_filter($this->tasks, fn($t) =>
            $t->getData('operator') === $operator &&
            $t->getData('task_state') === TaskState::DOING->value
        ));
    }
}

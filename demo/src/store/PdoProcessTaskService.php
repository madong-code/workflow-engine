<?php
/**
 * workflow demo · 流程任务服务（PDO + 原生 SQL）
 *
 * 与引擎 tests/fixture/memory/MemoryProcessTaskService 行为语义一致，
 * 并对齐生产插件 ProcessTaskService 的落库要点：
 * - createTask：任务行 + 参与人（每处理人一条 actor）+ DOING 历史，同事务
 * - finishProcessTask：任务完结 + 历史同步
 * - assignee 解析支持 ${var} / node.var / 逗号分隔多处理人 / 数组变量
 */

declare(strict_types=1);

namespace madong\workflow\demo\store;

use madong\workflow\demo\Id;
use madong\workflow\demo\RowDict;
use madong\workflow\context\ExecutionContext;
use madong\workflow\enum\{ProcessConst, TaskState, TaskType, PerformType};
use madong\workflow\interface\model\IProcessTask;
use madong\workflow\interface\services\{IProcessTaskService, IProcessTaskActorService, IProcessTaskHistoryService};
use madong\workflow\model\nodes\TaskNode;
use PDO;

final class PdoProcessTaskService implements IProcessTaskService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly IProcessTaskActorService $actorService,
        private readonly IProcessTaskHistoryService $historyService,
    ) {}

    public function createTask(TaskNode $taskNode, ExecutionContext $ctx): array
    {
        $operators = $this->resolveOperators($taskNode->assignee, $ctx);
        $primary   = $operators[0] ?? null;

        $row = [
            'id'                  => Id::next(),
            'process_instance_id' => $ctx->processInstanceId,
            'task_name'           => $taskNode->name,
            'display_name'        => $taskNode->displayName ?: $taskNode->name,
            'task_state'          => TaskState::DOING->value,
            'task_type'           => $taskNode->taskType?->value ?? TaskType::MAJOR->value,
            'perform_type'        => $taskNode->performType?->value ?? PerformType::NORMAL->value,
            'operator'            => $primary,
            'form_key'            => $taskNode->form,
            'submit_type'         => null,
            'comment'             => null,
            'create_time'         => time(),
            'finish_time'         => 0,
        ];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO wf_demo_task
                 (id, process_instance_id, task_name, display_name, task_state, task_type, perform_type, operator, form_key, submit_type, comment, create_time, finish_time)
                 VALUES
                 (:id, :process_instance_id, :task_name, :display_name, :task_state, :task_type, :perform_type, :operator, :form_key, :submit_type, :comment, :create_time, :finish_time)'
            );
            $stmt->execute($row);

            // 参与人（一个或多个办理人，各写一条 actor）
            foreach ($operators as $actorId) {
                $this->actorService->createTaskActor((string) $row['id'], $actorId, 0);
            }

            // 创建时即落历史（DOING），完结时再更新
            $task = RowDict::of($row);
            $this->historyService->createHistoryProcessTask($task);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [$task];
    }

    public function createCountersignTask(TaskNode $taskNode, ExecutionContext $ctx): array
    {
        $operators = $this->resolveOperators($taskNode->assignee, $ctx);
        if (empty($operators)) {
            // 无操作人列表时退化为单实例
            return $this->createTask($taskNode, $ctx);
        }

        // 记录会签操作人列表，供合并判断与轨迹追溯使用
        $prefix = ProcessConst::COUNTERSIGN_PREFIX . $taskNode->name . '_';
        $ctx->setVariable($prefix . ProcessConst::OPERATOR_LIST, array_values($operators));
        $ctx->setVariable($prefix . ProcessConst::NR_OF_COMPLETED, 0);
        // 持久化到实例变量（跨请求可见，对齐插件变量落库语义）
        $this->mergeInstanceVariables(
            $ctx->processInstanceId,
            [
                $prefix . ProcessConst::OPERATOR_LIST   => array_values($operators),
                $prefix . ProcessConst::NR_OF_COMPLETED => 0,
            ]
        );

        $tasks = [];
        foreach ($operators as $operator) {
            $row = [
                'id'                  => Id::next(),
                'process_instance_id' => $ctx->processInstanceId,
                'task_name'           => $taskNode->name,
                'display_name'        => $taskNode->displayName ?: $taskNode->name,
                'task_state'          => TaskState::DOING->value,
                'task_type'           => $taskNode->taskType?->value ?? TaskType::MAJOR->value,
                'perform_type'        => $taskNode->performType?->value ?? PerformType::COUNTERSIGN->value,
                'operator'            => (string) $operator,
                'form_key'            => $taskNode->form,
                'submit_type'         => null,
                'comment'             => null,
                'create_time'         => time(),
                'finish_time'         => 0,
            ];

            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO wf_demo_task
                     (id, process_instance_id, task_name, display_name, task_state, task_type, perform_type, operator, form_key, submit_type, comment, create_time, finish_time)
                     VALUES
                     (:id, :process_instance_id, :task_name, :display_name, :task_state, :task_type, :perform_type, :operator, :form_key, :submit_type, :comment, :create_time, :finish_time)'
                );
                $stmt->execute($row);

                $this->actorService->createTaskActor((string) $row['id'], (string) $operator, 0);

                $task = RowDict::of($row);
                $this->historyService->createHistoryProcessTask($task);

                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    public function getById(string $id): ?IProcessTask
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_task WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function finishProcessTask(string $id, string $operator, object $args): void
    {
        $submitType = null;
        $comment    = null;
        if (method_exists($args, 'getData')) {
            $submitType = $args->getData(ProcessConst::SUBMIT_TYPE);
            $comment    = $args->getData('comment');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE wf_demo_task
             SET task_state = :state, submit_type = :submit_type, comment = :comment, finish_time = :finish_time
             WHERE id = :id AND task_state = :doing'
        );
        $stmt->execute([
            'state'       => TaskState::FINISHED->value,
            'submit_type' => $submitType,
            'comment'     => is_scalar($comment) ? (string) $comment : null,
            'finish_time' => time(),
            'id'          => $id,
            'doing'       => TaskState::DOING->value,
        ]);

        // 同步历史状态（引擎内核不直接回调历史服务，由存储层对齐插件语义）
        $hist = $this->historyService->getByProcessTaskId($id);
        if ($hist !== null && method_exists($hist, 'getData') && (int) $hist->getData('task_state') === TaskState::DOING->value) {
            $stmt = $this->pdo->prepare(
                'UPDATE wf_demo_task_history SET task_state = :state, finish_time = :finish_time WHERE process_task_id = :id'
            );
            $stmt->execute([
                'state'       => TaskState::FINISHED->value,
                'finish_time' => time(),
                'id'          => $id,
            ]);
        }
    }

    public function withdrawTask(string $id, string $operator): void
    {
        $stmt = $this->pdo->prepare('UPDATE wf_demo_task SET task_state = :state WHERE id = :id');
        $stmt->execute(['state' => TaskState::CANCELED->value, 'id' => $id]);
    }

    public function isAllowed(IProcessTask $task, string $operator): bool
    {
        if ($task->getOperator() === $operator) {
            return true;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS c FROM wf_demo_task_actor WHERE process_task_id = :task_id AND actor_id = :actor_id'
        );
        $stmt->execute(['task_id' => $task->getId(), 'actor_id' => $operator]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getDoingTaskList(string $instanceId, string $taskNames): array
    {
        if ($taskNames === '') {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM wf_demo_task WHERE process_instance_id = :instance_id AND task_state = :doing ORDER BY create_time ASC, id ASC'
            );
            $stmt->execute(['instance_id' => $instanceId, 'doing' => TaskState::DOING->value]);
        } else {
            $names = array_values(array_filter(array_map('trim', explode(',', $taskNames)), fn($n) => $n !== ''));
            $placeholders = implode(', ', array_map(fn($i) => ":n{$i}", array_keys($names)));
            $params = ['instance_id' => $instanceId, 'doing' => TaskState::DOING->value];
            foreach ($names as $i => $name) {
                $params["n{$i}"] = $name;
            }
            $stmt = $this->pdo->prepare(
                "SELECT * FROM wf_demo_task
                 WHERE process_instance_id = :instance_id AND task_state = :doing AND task_name IN ({$placeholders})
                 ORDER BY create_time ASC, id ASC"
            );
            $stmt->execute($params);
        }

        return array_map(static fn(array $row) => RowDict::of($row), $stmt->fetchAll());
    }

    public function getTaskListByOperator(string $operator, array $filters = []): array
    {
        // 待办 = operator 直属 或 出现在 actor 表
        $sql = 'SELECT DISTINCT t.* FROM wf_demo_task t
                LEFT JOIN wf_demo_task_actor a ON a.process_task_id = t.id
                WHERE t.task_state = :doing AND (t.operator = :op OR a.actor_id = :op)
                ORDER BY t.create_time ASC, t.id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doing' => TaskState::DOING->value, 'op' => $operator]);
        return array_map(static fn(array $row) => RowDict::of($row), $stmt->fetchAll());
    }

    public function cancelDoingTasksByNode(string $instanceId, string $taskName, string $excludeTaskId, string $operator): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE wf_demo_task
             SET task_state = :canceled, finish_time = :t
             WHERE process_instance_id = :instance_id AND task_name = :task_name AND id <> :exclude_id AND task_state = :doing'
        );
        $stmt->execute([
            'canceled'    => TaskState::CANCELED->value,
            't'           => time(),
            'instance_id' => $instanceId,
            'task_name'   => $taskName,
            'exclude_id'  => $excludeTaskId,
            'doing'       => TaskState::DOING->value,
        ]);
        return $stmt->rowCount();
    }

    public function cancelAllDoingTasks(string $instanceId, string $operator): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE wf_demo_task
             SET task_state = :canceled, finish_time = :t
             WHERE process_instance_id = :instance_id AND task_state IN (:doing, :suspended)'
        );
        $stmt->execute([
            'canceled'    => TaskState::CANCELED->value,
            't'           => time(),
            'instance_id' => $instanceId,
            'doing'       => TaskState::DOING->value,
            'suspended'   => TaskState::SUSPENDED->value,
        ]);
        return $stmt->rowCount();
    }

    public function getCosignGroupState(IProcessTask $task): int
    {
        // 非会签任务（无协办组关联）走旧版同节点 sibling 兜底判断
        if ((int) $task->getPerformType() !== PerformType::COUNTERSIGN->value) {
            return self::COSIGN_NONE;
        }

        // 会签任务：同节点仍有 DOING 兄弟（排除自己）→ 等待，否则允许流转
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS c FROM wf_demo_task
             WHERE process_instance_id = :instance_id AND task_name = :task_name AND id <> :id AND task_state = :doing'
        );
        $stmt->execute([
            'instance_id' => $task->getProcessInstanceId(),
            'task_name'   => $task->getTaskName(),
            'id'          => $task->getId(),
            'doing'       => TaskState::DOING->value,
        ]);
        return (int) $stmt->fetchColumn() > 0 ? self::COSIGN_WAIT : self::COSIGN_FLOW;
    }

    /**
     * 解析 assignee 为处理人列表：
     * - ${var} / node.var 形式从流程变量取值（数组直接用，标量包装单元素）
     * - 完整键优先命中，其次点号尾段（apply.operator → operator）
     * - 非表达式形式按逗号分隔为多处理人
     */
    private function resolveOperators(?string $assignee, ExecutionContext $ctx): array
    {
        if ($assignee === null || $assignee === '') {
            return [];
        }

        if (preg_match('/^\$\{(\w+)\}$/', $assignee, $m)) {
            return $this->normalizeOperators($ctx->getVariable($m[1]));
        }

        $direct = $ctx->getVariable($assignee);
        if ($direct !== null && (!is_object($direct))) {
            return $this->normalizeOperators($direct);
        }

        if (str_contains($assignee, '.')) {
            $tail = substr($assignee, strrpos($assignee, '.') + 1);
            $tailVal = $ctx->getVariable($tail);
            if ($tailVal !== null && !is_object($tailVal)) {
                return $this->normalizeOperators($tailVal);
            }
        }

        return $this->normalizeOperators($assignee);
    }

    private function normalizeOperators(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn($v) => $v !== null && $v !== ''));
        }
        if (is_scalar($value) && (string) $value !== '') {
            return array_values(array_filter(
                array_map('trim', explode(',', (string) $value)),
                static fn($v) => $v !== ''
            ));
        }
        return [];
    }

    /** 合并写入实例变量（JSON 读改写，与 PdoProcessInstanceService::addVariable 语义一致） */
    private function mergeInstanceVariables(string $instanceId, array $vars): void
    {
        $stmt = $this->pdo->prepare('SELECT variable FROM wf_demo_instance WHERE id = :id');
        $stmt->execute(['id' => $instanceId]);
        $raw = $stmt->fetchColumn();
        $existing = ($raw === false || $raw === null) ? [] : (json_decode((string) $raw, true) ?: []);
        $merged = array_merge($existing, $vars);
        $stmt = $this->pdo->prepare('UPDATE wf_demo_instance SET variable = :v WHERE id = :id');
        $stmt->execute([
            'v'  => json_encode($merged, JSON_UNESCAPED_UNICODE),
            'id' => $instanceId,
        ]);
    }
}

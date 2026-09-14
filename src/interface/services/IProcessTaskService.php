<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessTask;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\context\ExecutionContext;

interface IProcessTaskService
{
    /** 任务无协办组关联（走旧版同节点 sibling 兜底判断） */
    public const COSIGN_NONE = 0;
    /** 协办组未办结：当前任务等待，不流转 */
    public const COSIGN_WAIT = 1;
    /** 协办组办结且为前加签/会签模式：锚点任务待恢复终审，禁止流转（服务层负责恢复） */
    public const COSIGN_RESUME = 2;
    /** 协办组办结且为后加签模式（或锚点终审）：允许流转 */
    public const COSIGN_FLOW = 3;

    public function createTask(TaskNode $taskNode, ExecutionContext $ctx): array;
    public function createCountersignTask(TaskNode $taskNode, ExecutionContext $ctx): array;
    public function getById(string $id): ?IProcessTask;
    public function finishProcessTask(string $id, string $operator, object $args): void;
    public function withdrawTask(string $id, string $operator): void;
    public function isAllowed(IProcessTask $task, string $operator): bool;
    public function getDoingTaskList(string $instanceId, string $taskNames): array;
    public function getTaskListByOperator(string $operator, array $filters = []): array;
    public function cancelDoingTasksByNode(string $instanceId, string $taskName, string $excludeTaskId, string $operator): int;

    /**
     * 流程结束兜底：取消实例内全部 DOING/SUSPENDED 残留任务
     * （并行分支/多出边场景下，实例走到结束节点时可能还有未办任务，需同步取消避免幽灵待办）
     */
    public function cancelAllDoingTasks(string $instanceId, string $operator): int;

    /**
     * 判断任务所处的协办组（加签/运行时会签）状态，驱动引擎是否流转。
     *
     * @param IProcessTask $task 当前正在办结/执行的任务
     * @return int COSIGN_NONE / COSIGN_WAIT / COSIGN_RESUME / COSIGN_FLOW
     */
    public function getCosignGroupState(IProcessTask $task): int;
}

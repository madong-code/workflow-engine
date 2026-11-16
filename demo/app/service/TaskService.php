<?php
/**
 * workflow demo · 任务业务服务（待办 / 已办 / 办理：同意 / 退回发起节点 / 拒绝终止）
 *
 * 待办/已办列表合并生效委托的委托人数据（行带 delegated_from 标记），
 * 办理时受托人以 act_as（任务归属人身份）执行引擎。
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\Engine;
use madong\workflow\enum\ProcessConst;
use madong\workflow\enum\SubmitType;
use madong\workflow\exception\FlowException;
use madong\workflow\interface\services\IProcessTaskService;
use function madong\workflow\demo\norm_row;

final class TaskService
{
    public function __construct(
        private Engine $engine,
        private PDO $pdo,
        private DelegateService $delegates,
    ) {
    }

    /** 可见视角：当前用户 + 委托给当前用户的生效委托人 */
    private function visibleOperators(string $operator): array
    {
        return array_values(array_unique(array_merge([$operator], $this->delegates->sourcesOf($operator))));
    }

    public function todos(string $operator): array
    {
        if ($operator === '') {
            throw new FlowException('operator 必填');
        }
        $taskService = $this->engine->getService(IProcessTaskService::class);
        $rows = [];
        foreach ($this->visibleOperators($operator) as $op) {
            foreach ($taskService->getTaskListByOperator($op) as $task) {
                $row = norm_row($task->getAll());
                if ($op !== $operator) {
                    $row['delegated_from'] = $op;
                }
                $iStmt = $this->pdo->prepare(
                    'SELECT display_name, operator, business_no, title FROM wf_demo_instance WHERE id = :id'
                );
                $iStmt->execute(['id' => $task->getProcessInstanceId()]);
                if ($inst = $iStmt->fetch()) {
                    // 流程列展示：实例标题优先（发起表单的 title），空则回退定义显示名
                    $row['instance_display_name'] = ($inst['title'] ?? '') !== '' ? $inst['title'] : $inst['display_name'];
                    $row['instance_operator']     = $inst['operator'];
                    $row['business_no']           = $inst['business_no'];
                }
                $rows[] = $row;
            }
        }
        usort($rows, static fn(array $a, array $b): int => ($b['create_time'] ?? 0) <=> ($a['create_time'] ?? 0));
        return $rows;
    }

    public function dones(string $operator): array
    {
        if ($operator === '') {
            throw new FlowException('operator 必填');
        }
        $operators = $this->visibleOperators($operator);
        $in = implode(', ', array_map(static fn(int $i): string => ':op' . $i, array_keys($operators)));
        $params = [];
        foreach ($operators as $i => $op) {
            $params['op' . $i] = $op;
        }
        $stmt = $this->pdo->prepare(
            'SELECT t.*, i.display_name AS define_display_name, i.operator AS instance_operator, i.state AS instance_state,
                    COALESCE(NULLIF(i.title, ""), i.display_name) AS instance_display_name
             FROM wf_demo_task t
             LEFT JOIN wf_demo_instance i ON i.id = t.process_instance_id
             WHERE t.task_state = 1 AND t.operator IN (' . $in . ')
             ORDER BY t.finish_time DESC, t.create_time DESC LIMIT 200'
        );
        $stmt->execute($params);
        $rows = array_map(static fn(array $r): array => norm_row($r), $stmt->fetchAll());
        // 受托视角标记：任务办理人为委托人时附 delegated_from（前端展示「受托自」徽标）
        foreach ($rows as &$row) {
            if (($row['operator'] ?? '') !== $operator) {
                $row['delegated_from'] = $row['operator'];
            }
        }
        unset($row);
        return $rows;
    }

    /** @return array{created_task_ids: list<string|null>} */
    public function complete(string $taskId, array $body): array
    {
        $operator = (string) ($body['operator'] ?? '');
        $action   = (string) ($body['action'] ?? 'agree');       // agree|reject|back
        $comment  = (string) ($body['comment'] ?? '');
        $vars     = is_array($body['vars'] ?? null) ? $body['vars'] : [];

        if ($operator === '') {
            throw new FlowException('operator 必填');
        }

        $taskService = $this->engine->getService(IProcessTaskService::class);
        $task = $taskService->getById($taskId);
        if ($task === null) {
            throw new FlowException('任务不存在');
        }
        // 受托代办（act_as）：非任务归属人需存在生效委托关系，以归属人身份执行引擎
        $taskOperator = method_exists($task, 'getOperator') ? (string) $task->getOperator() : '';
        if ($taskOperator !== '' && $operator !== $taskOperator) {
            if (!in_array($taskOperator, $this->delegates->sourcesOf($operator), true)) {
                throw new FlowException('无权办理该任务（无生效委托关系）');
            }
            $operator = $taskOperator;
        }
        if (!$taskService->isAllowed($task, $operator)) {
            throw new FlowException('无权办理该任务');
        }

        $vars[ProcessConst::SUBMIT_TYPE] = $action === 'reject'
            ? SubmitType::REJECT->value
            : SubmitType::AGREE->value;
        $vars['comment'] = $comment;

        $created = match ($action) {
            'reject' => $this->engine->rejectToEnd($taskId, $operator),          // 拒绝终止
            'back'   => $this->engine->rejectToFirst($taskId, $operator, $vars), // 退回发起节点
            default  => $this->engine->completeTask($taskId, $operator, $vars),  // 同意流转
        };

        $createdIds = array_map(
            static fn($t) => method_exists($t, 'getId') ? $t->getId() : null,
            $created
        );
        return ['created_task_ids' => array_values(array_filter($createdIds))];
    }
}

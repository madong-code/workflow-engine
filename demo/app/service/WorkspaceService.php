<?php
/**
 * workflow demo · 工作台业务服务（个人卡 + 全局览 + 近 7 天趋势 + 流程 Top + 状态分布 + 最近待办）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\enum\ProcessState;
use madong\workflow\enum\TaskState;

final class WorkspaceService
{
    public function __construct(
        private PDO $pdo,
        private TaskService $tasks,
    ) {
    }

    public function overview(string $operator): array
    {
        $todayStart = strtotime('today');

        // ---------- 个人维度卡（demo 无抄送场景，cc 恒 0，预留接入） ----------
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM wf_demo_instance WHERE operator = :op');
        $stmt->execute(['op' => $operator]);
        $personal = [
            'todo' => $this->countTasks($operator, TaskState::DOING->value),
            'done' => $this->countTasks($operator, TaskState::FINISHED->value),
            'mine' => (int) $stmt->fetchColumn(),
            'cc'   => 0,
        ];

        // ---------- 全局览 ----------
        $stateMap = [];
        foreach ($this->pdo->query('SELECT state, COUNT(*) AS c FROM wf_demo_instance GROUP BY state')->fetchAll() as $r) {
            $stateMap[(int) $r['state']] = (int) $r['c'];
        }
        $total    = array_sum($stateMap);
        $finished = $stateMap[ProcessState::FINISHED->value] ?? 0;
        $rejected = $stateMap[ProcessState::REJECTED->value] ?? 0;

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM wf_demo_instance WHERE create_time >= :t');
        $stmt->execute(['t' => $todayStart]);
        $todayCount = (int) $stmt->fetchColumn();

        // 平均办结时长（秒）：仅统计有结束时间的实例
        $avgSec = $this->pdo->query(
            'SELECT AVG(end_time - create_time) FROM wf_demo_instance WHERE end_time > 0 AND end_time >= create_time'
        )->fetchColumn();
        $avgSec = $avgSec === null ? 0 : (int) round((float) $avgSec);

        // 积压任务 = 全部待办；逾期任务 = 到达超过 24 小时仍未办理的待办
        $backlog = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM wf_demo_task WHERE task_state = ' . TaskState::DOING->value
        )->fetchColumn();
        $overdue = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM wf_demo_task WHERE task_state = ' . TaskState::DOING->value
            . ' AND create_time < ' . (time() - 86400)
        )->fetchColumn();

        $global = [
            'total'        => $total,
            'active'       => $stateMap[ProcessState::ACTIVE->value] ?? 0,
            'finished'     => $finished,
            'today'        => $todayCount,
            'avg_sec'      => $avgSec,
            'reject_rate'  => $total > 0 ? round($rejected / $total * 100, 1) : 0.0,
            'backlog'      => $backlog,
            'overdue'      => $overdue,
        ];

        return [
            'personal'     => $personal,
            'global'       => $global,
            'trend'        => $this->trend7days(),
            'top_defines'  => $this->topDefines(),
            'state_dist'   => $this->stateDist(),
            'recent_todos' => array_slice($this->tasks->todos($operator), 0, 5),
        ];
    }

    /** 近 7 天趋势（按自然日聚合发起/办结，PHP 侧分组避免 MySQL 时区差异） */
    private function trend7days(): array
    {
        $from = strtotime('-6 days', strtotime('today'));

        $stmt = $this->pdo->prepare('SELECT create_time FROM wf_demo_instance WHERE create_time >= :from');
        $stmt->execute(['from' => $from]);
        $started = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $stmt = $this->pdo->prepare('SELECT end_time FROM wf_demo_instance WHERE end_time > 0 AND end_time >= :from');
        $stmt->execute(['from' => $from]);
        $finishedRows = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $trend = [];
        for ($i = 0; $i < 7; $i++) {
            $ts  = strtotime('+' . $i . ' days', $from);
            $day = date('Y-m-d', $ts);
            $trend[] = [
                'date'     => date('m-d', $ts),
                'started'  => count(array_filter($started, static fn(int $t): bool => date('Y-m-d', $t) === $day)),
                'finished' => count(array_filter($finishedRows, static fn(int $t): bool => date('Y-m-d', $t) === $day)),
            ];
        }
        return $trend;
    }

    /** 流程 Top 5（按定义分组实例数 + 平均办结时长，未结束实例不计入均值） */
    private function topDefines(): array
    {
        $rows = $this->pdo->query(
            'SELECT display_name AS name, COUNT(*) AS c,'
            . ' AVG(CASE WHEN end_time > 0 AND end_time >= create_time THEN end_time - create_time END) AS avg_sec'
            . ' FROM wf_demo_instance GROUP BY display_name ORDER BY c DESC LIMIT 5'
        )->fetchAll();
        return array_map(static fn(array $r): array => [
            'name'    => (string) $r['name'],
            'count'   => (int) $r['c'],
            'avg_sec' => $r['avg_sec'] === null ? 0 : (int) round((float) $r['avg_sec']),
        ], $rows);
    }

    /** 状态分布（实例状态 → 数量） */
    private function stateDist(): array
    {
        $rows = $this->pdo->query('SELECT state, COUNT(*) AS c FROM wf_demo_instance GROUP BY state ORDER BY state')->fetchAll();
        return array_map(static fn(array $r): array => [
            'state' => (int) $r['state'],
            'count' => (int) $r['c'],
        ], $rows);
    }

    private function countTasks(string $operator, int $taskState): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM wf_demo_task WHERE operator = :op AND task_state = :ts'
        );
        $stmt->execute(['op' => $operator, 'ts' => $taskState]);
        return (int) $stmt->fetchColumn();
    }
}

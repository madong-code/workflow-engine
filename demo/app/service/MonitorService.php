<?php
/**
 * workflow demo · 流程监控业务服务（实例/任务状态统计 + 运行中实例）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\enum\ProcessState;
use function madong\workflow\demo\norm_row;

final class MonitorService
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function overview(): array
    {
        $instanceStates = $this->pdo->query(
            'SELECT state, COUNT(*) AS c FROM wf_demo_instance GROUP BY state'
        )->fetchAll();
        $taskStates = $this->pdo->query(
            'SELECT task_state, COUNT(*) AS c FROM wf_demo_task GROUP BY task_state'
        )->fetchAll();

        $stmt = $this->pdo->query(
            'SELECT id, define_name, display_name, operator, state, title, create_time
             FROM wf_demo_instance WHERE state = ' . ProcessState::ACTIVE->value . '
             ORDER BY create_time DESC LIMIT 50'
        );
        return [
            'instance_states' => $instanceStates,
            'task_states'     => $taskStates,
            'running'         => array_map(static fn(array $r): array => norm_row($r), $stmt->fetchAll()),
        ];
    }
}

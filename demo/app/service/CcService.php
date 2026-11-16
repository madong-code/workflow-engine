<?php
/**
 * workflow demo · 抄送业务服务（实例抄送通知 + 我的抄送列表）
 *
 * - 抄送为通知性质记录，不经过引擎流转；标题/发起人按抄送时刻快照落库
 * - 同一实例对同一被抄送人幂等（唯一键 uk_cc_once）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\exception\FlowException;
use madong\workflow\demo\model\Id;
use function madong\workflow\demo\norm_row;

final class CcService
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /** 抄送：body = { operators: ["dept_manager", ...] } */
    public function notify(string $instanceId, array $body): array
    {
        $operators = $body['operators'] ?? [];
        if (!is_array($operators) || $operators === []) {
            throw new FlowException('operators 必填（被抄送人列表）');
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, title, define_name, operator FROM wf_demo_instance WHERE id = :id'
        );
        $stmt->execute(['id' => $instanceId]);
        $instance = $stmt->fetch();
        if ($instance === false) {
            throw new FlowException('流程实例不存在');
        }

        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO wf_demo_cc_instance
             (id, process_instance_id, title, define_name, instance_operator, cc_operator, create_time)
             VALUES (:id, :iid, :title, :define_name, :iop, :ccop, :ctime)'
        );
        $now      = time();
        $inserted = 0;
        $this->pdo->beginTransaction();
        try {
            foreach (array_values(array_unique(array_filter(array_map('strval', $operators)))) as $ccOperator) {
                $insert->execute([
                    'id'          => Id::next(),
                    'iid'         => $instanceId,
                    'title'       => (string) $instance['title'],
                    'define_name' => (string) $instance['define_name'],
                    'iop'         => (string) $instance['operator'],
                    'ccop'        => $ccOperator,
                    'ctime'       => $now,
                ]);
                $inserted += $insert->rowCount();
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return ['cc_total' => count($operators), 'cc_inserted' => $inserted];
    }

    /** 我的抄送列表（按被抄送人） */
    public function list(string $operator): array
    {
        if ($operator === '') {
            throw new FlowException('operator 必填');
        }
        $stmt = $this->pdo->prepare(
            'SELECT c.*, i.state AS instance_state, i.business_no
             FROM wf_demo_cc_instance c
             LEFT JOIN wf_demo_instance i ON i.id = c.process_instance_id
             WHERE c.cc_operator = :operator
             ORDER BY c.create_time DESC, c.id DESC LIMIT 200'
        );
        $stmt->execute(['operator' => $operator]);
        return array_map(static fn(array $r): array => norm_row($r), $stmt->fetchAll());
    }
}

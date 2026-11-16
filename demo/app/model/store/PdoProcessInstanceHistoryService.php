<?php
/**
 * workflow demo · 流程实例历史服务（PDO + 原生 SQL）
 *
 * 与引擎 tests/fixture/memory/MemoryProcessInstanceHistoryService 行为语义一致。
 */

declare(strict_types=1);

namespace madong\workflow\demo\model\store;

use madong\workflow\demo\model\Id;
use madong\workflow\demo\model\RowDict;
use madong\workflow\interface\model\{IProcessInstance, IProcessInstanceHistory};
use madong\workflow\interface\services\IProcessInstanceHistoryService;
use PDO;

final class PdoProcessInstanceHistoryService implements IProcessInstanceHistoryService
{
    public function __construct(private readonly PDO $pdo) {}

    public function createHistoryProcessInstance(IProcessInstance $instance): IProcessInstanceHistory
    {
        $row = [
            'id'                  => Id::next(),
            'process_instance_id' => $instance->getId(),
            'process_define_id'   => $instance->getProcessDefineId(),
            'define_name'         => (string) $instance->getData('define_name', ''),
            'display_name'        => (string) $instance->getData('display_name', ''),
            'state'               => $instance->getState(),
            'operator'            => $instance->getOperator(),
            'create_time'         => time(),
            'end_time'            => 0,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO wf_demo_instance_history
             (id, process_instance_id, process_define_id, define_name, display_name, state, operator, create_time, end_time)
             VALUES
             (:id, :process_instance_id, :process_define_id, :define_name, :display_name, :state, :operator, :create_time, :end_time)'
        );
        $stmt->execute($row);

        return RowDict::of($row);
    }

    public function getById(string $id): ?IProcessInstanceHistory
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_instance_history WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function getByProcessInstanceId(string $processInstanceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_instance_history WHERE process_instance_id = :id ORDER BY create_time ASC, id ASC'
        );
        $stmt->execute(['id' => $processInstanceId]);
        return array_map(static fn(array $row) => RowDict::of($row), $stmt->fetchAll());
    }
}

<?php
/**
 * workflow demo · 任务参与人历史服务（PDO + 原生 SQL）
 */

declare(strict_types=1);

namespace madong\workflow\demo\store;

use madong\workflow\demo\Id;
use madong\workflow\interface\model\IProcessTaskActor;
use madong\workflow\interface\services\IProcessTaskActorHistoryService;
use PDO;

final class PdoProcessTaskActorHistoryService implements IProcessTaskActorHistoryService
{
    public function __construct(private readonly PDO $pdo) {}

    public function createHistoryTaskActor(IProcessTaskActor $actor): object
    {
        $row = [
            'id'                  => Id::next(),
            'process_task_id'     => $actor->getProcessTaskId(),
            'process_instance_id' => $actor->getProcessInstanceId(),
            'actor_id'            => $actor->getActorId(),
            'actor_type'          => $actor->getActorType(),
            'create_time'         => time(),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO wf_demo_task_actor_history
             (id, process_task_id, process_instance_id, actor_id, actor_type, create_time)
             VALUES (:id, :process_task_id, :process_instance_id, :actor_id, :actor_type, :create_time)'
        );
        $stmt->execute($row);

        return (object) $row;
    }

    public function getByTaskId(string $taskId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_task_actor_history WHERE process_task_id = :task_id ORDER BY create_time ASC, id ASC'
        );
        $stmt->execute(['task_id' => $taskId]);
        return $stmt->fetchAll();
    }
}

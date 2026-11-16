<?php
/**
 * workflow demo · 任务参与人服务（PDO + 原生 SQL）
 */

declare(strict_types=1);

namespace madong\workflow\demo\store;

use madong\workflow\demo\Id;
use madong\workflow\demo\RowDict;
use madong\workflow\interface\model\IProcessTaskActor;
use madong\workflow\interface\services\IProcessTaskActorService;
use PDO;

final class PdoProcessTaskActorService implements IProcessTaskActorService
{
    public function __construct(private readonly PDO $pdo) {}

    public function createTaskActor(string $taskId, string $actorId, int $actorType): IProcessTaskActor
    {
        $row = [
            'id'                  => Id::next(),
            'process_task_id'     => $taskId,
            'process_instance_id' => $this->resolveInstanceId($taskId),
            'actor_id'            => $actorId,
            'actor_type'          => $actorType,
            'create_time'         => time(),
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO wf_demo_task_actor (id, process_task_id, process_instance_id, actor_id, actor_type, create_time)
             VALUES (:id, :process_task_id, :process_instance_id, :actor_id, :actor_type, :create_time)'
        );
        $stmt->execute($row);

        return RowDict::of($row);
    }

    public function getActorsByTaskId(string $taskId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_task_actor WHERE process_task_id = :task_id ORDER BY create_time ASC, id ASC'
        );
        $stmt->execute(['task_id' => $taskId]);
        return array_map(static fn(array $row) => RowDict::of($row), $stmt->fetchAll());
    }

    public function removeActor(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM wf_demo_task_actor WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function resolveInstanceId(string $taskId): string
    {
        $stmt = $this->pdo->prepare('SELECT process_instance_id FROM wf_demo_task WHERE id = :id');
        $stmt->execute(['id' => $taskId]);
        return (string) ($stmt->fetchColumn() ?: '0');
    }
}

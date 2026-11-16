<?php
/**
 * workflow demo · 任务历史服务（PDO + 原生 SQL）
 *
 * 与引擎 tests/fixture/memory/MemoryProcessTaskHistoryService 行为语义一致。
 */

declare(strict_types=1);

namespace madong\workflow\demo\store;

use madong\workflow\demo\Id;
use madong\workflow\demo\RowDict;
use madong\workflow\interface\model\{IProcessTask, IProcessTaskHistory};
use madong\workflow\interface\services\IProcessTaskHistoryService;
use PDO;

final class PdoProcessTaskHistoryService implements IProcessTaskHistoryService
{
    public function __construct(private readonly PDO $pdo) {}

    public function createHistoryProcessTask(IProcessTask $task): IProcessTaskHistory
    {
        $row = [
            'id'                  => Id::next(),
            'process_task_id'     => $task->getId(),
            'process_instance_id' => $task->getProcessInstanceId(),
            'task_name'           => $task->getTaskName(),
            'display_name'        => (string) $task->getData('display_name', ''),
            'task_state'          => $task->getTaskState(),
            'submit_type'         => $task->getData('submit_type'),
            'comment'             => $task->getData('comment'),
            'operator'            => $task->getOperator(),
            'create_time'         => time(),
            'finish_time'         => 0,
            'end_time'            => 0,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO wf_demo_task_history
             (id, process_task_id, process_instance_id, task_name, display_name, task_state, submit_type, comment, operator, create_time, finish_time, end_time)
             VALUES
             (:id, :process_task_id, :process_instance_id, :task_name, :display_name, :task_state, :submit_type, :comment, :operator, :create_time, :finish_time, :end_time)'
        );
        $stmt->execute($row);

        return RowDict::of($row);
    }

    public function getById(string $id): ?IProcessTaskHistory
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_task_history WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function getByProcessTaskId(string $taskId): ?IProcessTaskHistory
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_task_history WHERE process_task_id = :task_id ORDER BY create_time DESC, id DESC LIMIT 1'
        );
        $stmt->execute(['task_id' => $taskId]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function getByProcessInstanceId(string $instanceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_task_history WHERE process_instance_id = :id ORDER BY create_time ASC, id ASC'
        );
        $stmt->execute(['id' => $instanceId]);
        return array_map(static fn(array $row) => RowDict::of($row), $stmt->fetchAll());
    }
}

<?php
/**
 * workflow demo · 流程实例服务（PDO + 原生 SQL）
 *
 * 与引擎 tests/fixture/memory/MemoryProcessInstanceService 行为语义一致。
 */

declare(strict_types=1);

namespace madong\workflow\demo\model\store;

use madong\workflow\demo\model\Id;
use madong\workflow\demo\model\RowDict;
use madong\workflow\enum\ProcessState;
use madong\workflow\interface\model\{IProcessDefine, IProcessInstance};
use madong\workflow\interface\services\IProcessInstanceService;
use PDO;

final class PdoProcessInstanceService implements IProcessInstanceService
{
    public function __construct(private readonly PDO $pdo) {}

    /** 兼容引擎 Dict（get）与实现 getData 的参数字典读取 */
    private static function argOf(object $args, string $key, mixed $default = null): mixed
    {
        if (method_exists($args, 'getData')) {
            return $args->getData($key, $default);
        }
        return method_exists($args, 'get') ? $args->get($key, $default) : $default;
    }

    public function createProcessInstance(
        IProcessDefine $processDefine,
        string $operator,
        object $args,
        ?string $parentId = null,
        ?string $parentNodeName = null,
    ): IProcessInstance {
        $vars = [];
        if (method_exists($args, 'toArray')) {
            $vars = (array) $args->toArray();
        } elseif (method_exists($args, 'getAll')) {
            $vars = (array) $args->getAll();
        }

        $row = [
            'id'                 => Id::next(),
            'process_define_id'  => $processDefine->getId(),
            'define_name'        => $processDefine->getName(),
            'display_name'       => $processDefine->getDisplayName(),
            'business_no'        => self::argOf($args, 'business_no') ?: null,
            'title'              => (string) self::argOf($args, 'title', ''),
            'state'              => ProcessState::ACTIVE->value,
            'operator'           => $operator,
            'variable'           => json_encode($vars, JSON_UNESCAPED_UNICODE),
            'parent_id'          => $parentId,
            'parent_node_name'   => $parentNodeName,
            'create_time'        => time(),
            'end_time'           => 0,
        ];

        $sql = 'INSERT INTO wf_demo_instance
                (id, process_define_id, define_name, display_name, business_no, title, state, operator, variable, parent_id, parent_node_name, create_time, end_time)
                VALUES
                (:id, :process_define_id, :define_name, :display_name, :business_no, :title, :state, :operator, :variable, :parent_id, :parent_node_name, :create_time, :end_time)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);

        return RowDict::of($row);
    }

    public function getById(string $id): ?IProcessInstance
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_instance WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function finishProcessInstance(string $id): void
    {
        $this->changeState($id, ProcessState::FINISHED->value);
    }

    public function rejectProcessInstance(string $id): void
    {
        $this->changeState($id, ProcessState::REJECTED->value);
    }

    public function suspendProcessInstance(string $id): void
    {
        $this->changeState($id, ProcessState::SUSPENDED->value);
    }

    public function activateProcessInstance(string $id): void
    {
        $this->changeState($id, ProcessState::ACTIVE->value);
    }

    public function addVariable(string $id, array $vars): void
    {
        $existing = $this->rawVariable($id);
        $merged = array_merge($existing, $vars);
        $stmt = $this->pdo->prepare('UPDATE wf_demo_instance SET variable = :v WHERE id = :id');
        $stmt->execute(['v' => json_encode($merged, JSON_UNESCAPED_UNICODE), 'id' => $id]);
    }

    public function removeVariable(string $id, array $keys): void
    {
        $existing = $this->rawVariable($id);
        foreach ($keys as $k) {
            unset($existing[$k]);
        }
        $stmt = $this->pdo->prepare('UPDATE wf_demo_instance SET variable = :v WHERE id = :id');
        $stmt->execute(['v' => json_encode($existing, JSON_UNESCAPED_UNICODE), 'id' => $id]);
    }

    private function changeState(string $id, int $state): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE wf_demo_instance SET state = :s, end_time = CASE WHEN :s IN (1, 2, 4, 5) THEN :t ELSE end_time END WHERE id = :id'
        );
        $stmt->execute(['s' => $state, 't' => time(), 'id' => $id]);
    }

    private function rawVariable(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT variable FROM wf_demo_instance WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $raw = $stmt->fetchColumn();
        return $raw === false || $raw === null ? [] : (json_decode((string) $raw, true) ?: []);
    }
}

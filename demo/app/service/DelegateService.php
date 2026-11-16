<?php
/**
 * workflow demo · 委托业务服务（审批委托：把待办委托给他人代办）
 *
 * - source_operator 委托人 / target_operator 受托人 / define_name 空表示全部流程
 * - 生效中的委托（status=1）会使待办/已办列表合并展示委托人数据并允许受托代办（act_as）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\exception\FlowException;
use madong\workflow\demo\model\Id;
use function madong\workflow\demo\norm_row;

final class DelegateService
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /** 全部委托关系（委托管理页展示，前端按 operator 区分「我委托的 / 委托给我的」） */
    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT d.* FROM wf_demo_delegate d ORDER BY d.create_time DESC, d.id DESC LIMIT 200'
        );
        return array_map(static fn(array $r): array => norm_row($r), $stmt->fetchAll());
    }

    /** 新建委托：body = { operator(委托人), targetOperator(受托人), defineName? } */
    public function create(array $body): array
    {
        $source    = (string) ($body['operator'] ?? '');
        $target    = (string) ($body['targetOperator'] ?? '');
        $defineName = trim((string) ($body['defineName'] ?? ''));
        if ($source === '' || $target === '') {
            throw new FlowException('operator / targetOperator 必填');
        }
        if ($source === $target) {
            throw new FlowException('不能委托给自己');
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM wf_demo_user WHERE name = :name');
        $stmt->execute(['name' => $target]);
        if ((int) $stmt->fetch()['c'] === 0) {
            throw new FlowException('受托人不存在：' . $target);
        }
        if ($defineName !== '') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM wf_demo_define WHERE name = :name');
            $stmt->execute(['name' => $defineName]);
            if ((int) $stmt->fetch()['c'] === 0) {
                throw new FlowException('限定流程不存在：' . $defineName);
            }
        }

        $id = Id::next();
        $stmt = $this->pdo->prepare(
            'INSERT INTO wf_demo_delegate (id, source_operator, target_operator, define_name, status, create_time)
             VALUES (:id, :source, :target, :define_name, 1, :ctime)'
        );
        $stmt->execute([
            'id'          => $id,
            'source'      => $source,
            'target'      => $target,
            'define_name' => $defineName,
            'ctime'       => time(),
        ]);
        return $this->getById((string) $id);
    }

    public function toggle(string $id): array
    {
        $stmt = $this->pdo->prepare('UPDATE wf_demo_delegate SET status = 1 - status WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $this->getById($id);
        if ($row === null) {
            throw new FlowException('委托记录不存在');
        }
        return $row;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM wf_demo_delegate WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** 生效委托：受托人 target 当前可代办的委托人列表 */
    public function sourcesOf(string $target): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT source_operator FROM wf_demo_delegate WHERE target_operator = :target AND status = 1'
        );
        $stmt->execute(['target' => $target]);
        return array_column($stmt->fetchAll(), 'source_operator');
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_delegate WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : norm_row($row);
    }
}

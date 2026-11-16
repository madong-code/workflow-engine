<?php
/**
 * workflow demo · 流程定义服务（PDO + 原生 SQL）
 *
 * 与引擎 tests/fixture/memory/MemoryProcessDefineService 行为语义一致，
 * 存储层替换为 wf_demo_define 表；getProcessModel 复用引擎 ModelParser。
 */

declare(strict_types=1);

namespace madong\workflow\demo\model\store;

use madong\workflow\demo\model\Id;
use madong\workflow\demo\model\RowDict;
use madong\workflow\interface\model\IProcessDefine;
use madong\workflow\interface\services\IProcessDefineService;
use madong\workflow\model\ProcessModel;
use madong\workflow\parser\{ModelParser, NodeParserRegistry, DesignerParserRegistry};
use madong\workflow\parser\handler\{
    StartParser, EndParser, TaskParser, DecisionParser,
    ForkParser, JoinParser, CustomParser, SubProcessParser
};
use madong\workflow\parser\lf\LfDesignerParser;
use PDO;

final class PdoProcessDefineService implements IProcessDefineService
{
    private ?NodeParserRegistry $registry = null;
    private ?DesignerParserRegistry $designerRegistry = null;

    public function __construct(private readonly PDO $pdo) {}

    public function createProcessDefine(array $data): IProcessDefine
    {
        $now = time();
        $row = [
            'id'           => (string) ($data['id'] ?? Id::next()),
            'name'         => (string) ($data['name'] ?? ''),
            'display_name' => (string) ($data['display_name'] ?? ''),
            'category'     => (string) ($data['category'] ?? ''),
            'state'        => (int) ($data['state'] ?? 0),
            'is_active'    => (int) ($data['is_active'] ?? 0),
            'version'      => (int) ($data['version'] ?? 1),
            'content'      => isset($data['content'])
                ? (is_array($data['content']) ? json_encode($data['content'], JSON_UNESCAPED_UNICODE) : (string) $data['content'])
                : null,
            'create_time'  => $now,
            'update_time'  => $now,
        ];

        $sql = 'INSERT INTO wf_demo_define (id, name, display_name, category, state, is_active, version, content, create_time, update_time)
                VALUES (:id, :name, :display_name, :category, :state, :is_active, :version, :content, :create_time, :update_time)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($row);

        return RowDict::of($row);
    }

    public function updateProcessDefine(string $id, array $data): IProcessDefine
    {
        $existing = $this->getById($id);
        if ($existing === null) {
            throw new \RuntimeException("流程定义 [{$id}] 不存在");
        }

        $sets   = ['update_time = :update_time'];
        $params = ['update_time' => time()];
        $allowed = ['name', 'display_name', 'category', 'state', 'is_active', 'version', 'content'];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $value = $data[$col];
            if ($col === 'content' && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $sets[] = "{$col} = :{$col}";
            $params[$col] = $value;
        }

        $params['id'] = $id;
        $stmt = $this->pdo->prepare('UPDATE wf_demo_define SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);

        return $this->getById($id) ?? $existing;
    }

    public function getById(string $id): ?IProcessDefine
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_define WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function getProcessModel(string $defineId): ?ProcessModel
    {
        $define = $this->getById($defineId);
        if ($define === null) {
            return null;
        }
        $content = $define->getContent();
        if ($content === null || $content === '') {
            return null;
        }

        $parser = new ModelParser($this->getRegistry(), $this->getDesignerRegistry());
        return $parser->parse($content);
    }

    public function getProcessDefineByVersion(string $name, int $version): ?IProcessDefine
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_define WHERE name = :name AND version = :version ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['name' => $name, 'version' => $version]);
        $row = $stmt->fetch();
        return $row === false ? null : RowDict::of($row);
    }

    public function deleteProcessDefine(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM wf_demo_define WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function publishProcessDefine(string $id): IProcessDefine
    {
        $define = $this->getById($id);
        if ($define === null) {
            throw new \RuntimeException("流程定义 [{$id}] 不存在");
        }

        $this->pdo->beginTransaction();
        try {
            // 同名其他版本退位
            $stmt = $this->pdo->prepare('UPDATE wf_demo_define SET is_active = 0 WHERE name = :name AND id <> :id');
            $stmt->execute(['name' => $define->getName(), 'id' => $id]);

            $stmt = $this->pdo->prepare(
                'UPDATE wf_demo_define SET state = 1, is_active = 1, update_time = :t WHERE id = :id'
            );
            $stmt->execute(['t' => time(), 'id' => $id]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getById($id) ?? $define;
    }

    public function disableProcessDefine(string $id): IProcessDefine
    {
        return $this->updateProcessDefine($id, ['state' => 2]);
    }

    private function getRegistry(): NodeParserRegistry
    {
        if ($this->registry === null) {
            $this->registry = new NodeParserRegistry();
            $this->registry->registerBatch([
                'start'        => StartParser::class,
                'end'          => EndParser::class,
                'task'         => TaskParser::class,
                'decision'     => DecisionParser::class,
                'fork'         => ForkParser::class,
                'join'         => JoinParser::class,
                'custom'       => CustomParser::class,
                'wfSubProcess' => SubProcessParser::class,
            ]);
        }
        return $this->registry;
    }

    private function getDesignerRegistry(): DesignerParserRegistry
    {
        if ($this->designerRegistry === null) {
            $this->designerRegistry = new DesignerParserRegistry();
            $this->designerRegistry->register(new LfDesignerParser());
        }
        return $this->designerRegistry;
    }
}

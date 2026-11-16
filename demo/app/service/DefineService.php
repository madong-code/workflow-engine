<?php
/**
 * workflow demo · 流程定义业务服务（编排引擎 IProcessDefineService + PDO 直查）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\Engine;
use madong\workflow\exception\FlowException;
use madong\workflow\interface\services\IProcessDefineService;
use function madong\workflow\demo\norm_row;

final class DefineService
{
    public function __construct(
        private Engine $engine,
        private PDO $pdo,
    ) {
    }

    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, display_name, category, state, is_active, version, create_time, update_time
             FROM wf_demo_define ORDER BY update_time DESC'
        );
        return array_map(static fn(array $r): array => norm_row($r), $stmt->fetchAll());
    }

    public function create(array $body): array
    {
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new FlowException('name 必填');
        }
        $defineService = $this->engine->getService(IProcessDefineService::class);
        $define = $defineService->createProcessDefine([
            'name'         => $name,
            'display_name' => (string) ($body['display_name'] ?? $name),
            'category'     => (string) ($body['category'] ?? ''),
            'content'      => $body['content'] ?? null,
        ]);
        return norm_row($define->getAll());
    }

    public function detail(string $id): array
    {
        $defineService = $this->engine->getService(IProcessDefineService::class);
        $define = $defineService->getById($id);
        if ($define === null) {
            throw new FlowException('流程定义不存在');
        }
        $row = norm_row($define->getAll());
        $row['content_json'] = json_decode((string) $define->getContent(), true);
        return $row;
    }

    public function update(string $id, array $body): array
    {
        $data = [];
        foreach (['display_name', 'category', 'content'] as $key) {
            if (array_key_exists($key, $body)) {
                $data[$key] = $body[$key];
            }
        }
        $defineService = $this->engine->getService(IProcessDefineService::class);
        $define = $defineService->updateProcessDefine($id, $data);
        return norm_row($define->getAll());
    }

    public function delete(string $id): void
    {
        $defineService = $this->engine->getService(IProcessDefineService::class);
        $defineService->deleteProcessDefine($id);
    }

    public function publish(string $id): array
    {
        $defineService = $this->engine->getService(IProcessDefineService::class);
        $define = $defineService->publishProcessDefine($id);
        return norm_row($define->getAll());
    }
}

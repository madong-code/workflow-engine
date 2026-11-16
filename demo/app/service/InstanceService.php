<?php
/**
 * workflow demo · 流程实例业务服务（发起 / 我的申请列表 / 详情轨迹与高亮）
 */

declare(strict_types=1);

namespace madong\workflow\demo\service;

use PDO;
use madong\workflow\Engine;
use madong\workflow\enum\ProcessState;
use madong\workflow\exception\FlowException;
use function madong\workflow\demo\norm_row;

final class InstanceService
{
    public function __construct(
        private Engine $engine,
        private PDO $pdo,
    ) {
    }

    public function start(array $body): array
    {
        $defineId = (string) ($body['defineId'] ?? $body['define_id'] ?? '');
        $operator = (string) ($body['operator'] ?? '');
        $vars     = is_array($body['vars'] ?? null) ? $body['vars'] : [];

        if ($defineId === '' || $operator === '') {
            throw new FlowException('defineId / operator 必填');
        }
        // 发起人写入变量（apply 节点 assignee=apply.operator 按尾段解析）
        if (!isset($vars['operator'])) {
            $vars['operator'] = $operator;
        }
        // title 变量会随实例落库，便于列表展示

        $instance = $this->engine->startProcess($defineId, $operator, $vars);
        return norm_row($instance->getAll());
    }

    public function list(?string $operator, ?int $state): array
    {
        $sql    = 'SELECT * FROM wf_demo_instance WHERE 1=1';
        $params = [];
        if ($operator !== null && $operator !== '') {
            $sql .= ' AND operator = :operator';
            $params['operator'] = $operator;
        }
        if ($state !== null) {
            $sql .= ' AND state = :state';
            $params['state'] = $state;
        }
        $sql .= ' ORDER BY create_time DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map(static fn(array $r): array => norm_row($r), $stmt->fetchAll());

        // 附当前运行中节点（便于列表展示）
        foreach ($rows as &$row) {
            $stmt = $this->pdo->prepare(
                'SELECT DISTINCT task_name FROM wf_demo_task
                 WHERE process_instance_id = :id AND task_state = :doing'
            );
            $stmt->execute(['id' => $row['id'], 'doing' => 0]);
            $row['active_nodes'] = array_column($stmt->fetchAll(), 'task_name');
        }
        unset($row);
        return $rows;
    }

    /** 实例详情：任务轨迹（含参与人）+ 流程图高亮（已走/当前节点） */
    public function detail(string $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wf_demo_instance WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $instance = $stmt->fetch();
        if ($instance === false) {
            throw new FlowException('流程实例不存在');
        }
        $instance = norm_row($instance);
        $instance['variable_json'] = json_decode((string) $instance['variable'], true);

        // 任务轨迹（含取消，参与人列表）
        $stmt = $this->pdo->prepare(
            'SELECT * FROM wf_demo_task WHERE process_instance_id = :id ORDER BY create_time ASC, id ASC'
        );
        $stmt->execute(['id' => $id]);
        $tasks = [];
        foreach ($stmt->fetchAll() as $trow) {
            $trow = norm_row($trow);
            $aStmt = $this->pdo->prepare('SELECT actor_id FROM wf_demo_task_actor WHERE process_task_id = :tid');
            $aStmt->execute(['tid' => $trow['id']]);
            $trow['actors'] = array_column($aStmt->fetchAll(), 'actor_id');
            $tasks[] = $trow;
        }

        // 流程图高亮：已走节点（办结）与当前节点（运行中）
        $historyNodeNames = [];
        $activeNodeNames  = [];
        foreach ($tasks as $t) {
            if ((int) $t['task_state'] === 1) {           // FINISHED
                $historyNodeNames[$t['task_name']] = true;
            } elseif ((int) $t['task_state'] === 0) {     // DOING
                $activeNodeNames[$t['task_name']] = true;
            }
        }

        // 依据流程定义图推断非任务节点的经过状态（viewer 端线段高亮由两端节点推导，
        // 缺失 start/decision/custom/end 会导致开始节点与相邻连线不着色）：
        // 开始节点：实例存在即已通过；结束节点：正常办结（FINISHED）或拒绝终止（rejectToEnd 直达 end）均已到达；
        // 其余非任务节点（网关/自定义/分支聚合）沿出边命中已到达节点（含 end，覆盖直达 end 的网关路径）则视为已过
        $graph = $this->loadDefineGraph((string) $instance['process_define_id']);
        $historyEdgeNames = [];
        if ($graph !== null) {
            $state = (int) $instance['state'];
            if ($state === ProcessState::FINISHED->value || $state === ProcessState::REJECTED->value) {
                foreach ($graph['types'] as $nodeId => $type) {
                    if ($type === 'ingenious:end') {
                        $historyNodeNames[$nodeId] = true;
                    }
                }
            }
            $reached = $historyNodeNames + $activeNodeNames;
            foreach ($graph['types'] as $nodeId => $type) {
                if ($type === 'ingenious:task' || isset($historyNodeNames[$nodeId])) {
                    continue;
                }
                if ($type === 'ingenious:start' || $this->reachesReachedTask($nodeId, $graph['outs'], $reached)) {
                    $historyNodeNames[$nodeId] = true;
                }
            }

            // 已走过连线（canvas viewer 需显式传 history_edge_names，dingtalk 模式由两端节点推导）：
            // 两端均已到达即视为走过——分支未走的边因 target 未到达保持默认色；
            // 并行/会签的多条出入边自然全亮；退回重走不回退已亮线段
            $reached = $historyNodeNames + $activeNodeNames;
            foreach ($graph['edges'] as $e) {
                if (isset($reached[$e['source']]) && isset($reached[$e['target']])) {
                    $historyEdgeNames[] = $e['id'];
                }
            }
        }

        return [
            'instance'           => $instance,
            'tasks'              => $tasks,
            'history_node_names' => array_keys($historyNodeNames),
            'active_node_names'  => array_keys($activeNodeNames),
            'history_edge_names' => $historyEdgeNames,
        ];
    }

    /** 读取流程定义 content 图：节点 id → type 映射 + 邻接表 + 边列表（供线段高亮） */
    private function loadDefineGraph(string $defineId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT content FROM wf_demo_define WHERE id = :id');
        $stmt->execute(['id' => $defineId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || trim((string) $raw) === '') {
            return null;
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json) || empty($json['nodes'])) {
            return null;
        }
        $types = [];
        foreach ($json['nodes'] as $n) {
            if (!empty($n['id']) && !empty($n['type'])) {
                $types[(string) $n['id']] = (string) $n['type'];
            }
        }
        $outs = [];
        $edges = [];
        foreach ($json['edges'] ?? [] as $e) {
            if (empty($e['sourceNodeId']) || empty($e['targetNodeId'])) {
                continue;
            }
            $outs[(string) $e['sourceNodeId']][] = (string) $e['targetNodeId'];
            if (!empty($e['id'])) {
                $edges[] = [
                    'id'     => (string) $e['id'],
                    'source' => (string) $e['sourceNodeId'],
                    'target' => (string) $e['targetNodeId'],
                ];
            }
        }
        return ['types' => $types, 'outs' => $outs, 'edges' => $edges];
    }

    /** 非任务节点沿出边正向可达首个已到达任务节点（BFS，visited 防环） */
    private function reachesReachedTask(string $nodeId, array $outs, array $reached): bool
    {
        $seen  = [$nodeId => true];
        $queue = [$nodeId];
        while ($queue !== []) {
            foreach ($outs[array_shift($queue)] ?? [] as $next) {
                if (isset($seen[$next])) {
                    continue;
                }
                $seen[$next] = true;
                if (isset($reached[$next])) {
                    return true;
                }
                $queue[] = $next;
            }
        }
        return false;
    }
}

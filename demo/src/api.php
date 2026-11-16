<?php
/**
 * workflow demo · REST JSON API（原生 PHP，无框架依赖）
 *
 * 端点总览（风格对齐插件 plugin/workflow api.md）：
 *   GET    /api/users                       演示用户列表
 *   GET    /api/defines                     流程定义列表
 *   GET    /api/defines/{id}                流程定义详情（含 content）
 *   POST   /api/defines                     新建流程定义
 *   PUT    /api/defines/{id}                更新流程定义
 *   DELETE /api/defines/{id}                删除流程定义
 *   POST   /api/defines/{id}/publish        发布流程定义
 *   POST   /api/instances                   发起流程（engine->startProcess）
 *   GET    /api/instances?operator=         我的申请列表
 *   GET    /api/instances/{id}              实例详情（任务轨迹 + 流程图高亮）
 *   GET    /api/todos?operator=             待办任务
 *   GET    /api/dones?operator=             已办任务
 *   POST   /api/tasks/{id}/complete         办理任务（action=agree|reject|back）
 *   GET    /api/monitor/overview            流程监控统计
 *
 * 统一响应 {code, msg, data}；code=0 成功。
 */

declare(strict_types=1);

namespace madong\workflow\demo;

require_once __DIR__ . '/bootstrap.php';

use madong\workflow\demo\Database;
use madong\workflow\demo\Seeds;
use madong\workflow\Engine;
use madong\workflow\enum\{ProcessConst, ProcessState, SubmitType};
use madong\workflow\exception\FlowException;
use madong\workflow\interface\services\IProcessTaskService;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * 统一 JSON 输出（bigint id 以字符串传输，避免 JS 精度丢失）
 */
function json_out(mixed $data = null, string $msg = 'ok', int $code = 0, int $http = 200): never
{
    http_response_code($http);
    echo json_encode(
        ['code' => $code, 'msg' => $msg, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

/** 请求 body（JSON） */
function body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

/** 路由路径（去掉 /api 前缀） */
function route(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $uri = preg_replace('#^/api#', '', $uri) ?: '/';
    return rtrim($uri, '/') ?: '/';
}

/** 实体标准化：字符串 id + int 时间戳透传 */
function norm_row(array $row): array
{
    foreach ($row as $k => $v) {
        if (is_int($v) || (is_string($v) && preg_match('/^-?\d{10,}$/', $v) && str_contains($k, 'id'))) {
            $row[$k] = (string) $v;
        }
    }
    return $row;
}

$pdo    = Database::pdo();
$engine = engine();
ensureDatabase();

try {
    $path = route();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ---------- 用户 ----------
    if ($path === '/users' && $method === 'GET') {
        $list = [];
        foreach (Seeds::users() as $name => $displayName) {
            $list[] = ['name' => $name, 'display_name' => $displayName];
        }
        json_out($list);
    }

    // ---------- 流程定义 ----------
    if ($path === '/defines' && $method === 'GET') {
        $stmt = $pdo->query(
            'SELECT id, name, display_name, category, state, is_active, version, create_time, update_time
             FROM wf_demo_define ORDER BY update_time DESC'
        );
        json_out(array_map('madong\workflow\demo\norm_row', $stmt->fetchAll()));
    }

    if ($path === '/defines' && $method === 'POST') {
        $b = body();
        $name = trim((string) ($b['name'] ?? ''));
        if ($name === '') {
            json_out(null, 'name 必填', 1, 200);
        }
        $defineService = $engine->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $define = $defineService->createProcessDefine([
            'name'         => $name,
            'display_name' => (string) ($b['display_name'] ?? $name),
            'category'     => (string) ($b['category'] ?? ''),
            'content'      => $b['content'] ?? null,
        ]);
        json_out(norm_row($define->getAll()));
    }

    if (preg_match('#^/defines/([\w-]+)/publish$#', $path, $m) && $method === 'POST') {
        $defineService = $engine->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $define = $defineService->publishProcessDefine($m[1]);
        json_out(norm_row($define->getAll()));
    }

    if (preg_match('#^/defines/([\w-]+)$#', $path, $m)) {
        $id = $m[1];
        $defineService = $engine->getService(\madong\workflow\interface\services\IProcessDefineService::class);

        if ($method === 'GET') {
            $define = $defineService->getById($id);
            if ($define === null) {
                json_out(null, '流程定义不存在', 1, 404);
            }
            $row = norm_row($define->getAll());
            $row['content_json'] = json_decode((string) $define->getContent(), true);
            json_out($row);
        }

        if ($method === 'PUT') {
            $b    = body();
            $data = [];
            foreach (['display_name', 'category', 'content'] as $key) {
                if (array_key_exists($key, $b)) {
                    $data[$key] = $b[$key];
                }
            }
            $define = $defineService->updateProcessDefine($id, $data);
            json_out(norm_row($define->getAll()));
        }

        if ($method === 'DELETE') {
            $defineService->deleteProcessDefine($id);
            json_out(null);
        }
    }

    // ---------- 发起流程 ----------
    if ($path === '/instances' && $method === 'POST') {
        $b        = body();
        $defineId = (string) ($b['defineId'] ?? $b['define_id'] ?? '');
        $operator = (string) ($b['operator'] ?? '');
        $vars     = $b['vars'] ?? [];

        if ($defineId === '' || $operator === '') {
            json_out(null, 'defineId / operator 必填', 1, 200);
        }
        if (!is_array($vars)) {
            $vars = [];
        }
        // 发起人写入变量（apply 节点 assignee=apply.operator 按尾段解析）
        if (!isset($vars['operator'])) {
            $vars['operator'] = $operator;
        }
        // title 变量会随实例落库，便于列表展示

        $instance = $engine->startProcess($defineId, $operator, $vars);
        json_out(norm_row($instance->getAll()));
    }

    // ---------- 我的申请 ----------
    if ($path === '/instances' && $method === 'GET') {
        $operator = trim((string) ($_GET['operator'] ?? ''));
        $state    = isset($_GET['state']) ? (int) $_GET['state'] : null;

        $sql    = 'SELECT * FROM wf_demo_instance WHERE 1=1';
        $params = [];
        if ($operator !== '') {
            $sql .= ' AND operator = :operator';
            $params['operator'] = $operator;
        }
        if ($state !== null) {
            $sql .= ' AND state = :state';
            $params['state'] = $state;
        }
        $sql .= ' ORDER BY create_time DESC LIMIT 200';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map('madong\workflow\demo\norm_row', $stmt->fetchAll());

        // 附当前运行中节点（便于列表展示）
        foreach ($rows as &$row) {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT task_name FROM wf_demo_task
                 WHERE process_instance_id = :id AND task_state = :doing'
            );
            $stmt->execute(['id' => $row['id'], 'doing' => 0]);
            $row['active_nodes'] = array_column($stmt->fetchAll(), 'task_name');
        }
        unset($row);
        json_out($rows);
    }

    // ---------- 实例详情（轨迹 + 高亮） ----------
    if (preg_match('#^/instances/([\w-]+)$#', $path, $m) && $method === 'GET') {
        $id = $m[1];
        $stmt = $pdo->prepare('SELECT * FROM wf_demo_instance WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $instance = $stmt->fetch();
        if ($instance === false) {
            json_out(null, '流程实例不存在', 1, 404);
        }
        $instance = norm_row($instance);
        $instance['variable_json'] = json_decode((string) $instance['variable'], true);

        // 任务轨迹（含取消，参与人列表）
        $stmt = $pdo->prepare(
            'SELECT * FROM wf_demo_task WHERE process_instance_id = :id ORDER BY create_time ASC, id ASC'
        );
        $stmt->execute(['id' => $id]);
        $tasks = [];
        foreach ($stmt->fetchAll() as $trow) {
            $trow = norm_row($trow);
            $aStmt = $pdo->prepare('SELECT actor_id FROM wf_demo_task_actor WHERE process_task_id = :tid');
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

        json_out([
            'instance'           => $instance,
            'tasks'              => $tasks,
            'history_node_names' => array_keys($historyNodeNames),
            'active_node_names'  => array_keys($activeNodeNames),
        ]);
    }

    // ---------- 待办 / 已办 ----------
    if ($path === '/todos' && $method === 'GET') {
        $operator = trim((string) ($_GET['operator'] ?? ''));
        if ($operator === '') {
            json_out(null, 'operator 必填', 1, 200);
        }
        $taskService = $engine->getService(IProcessTaskService::class);
        $rows = [];
        foreach ($taskService->getTaskListByOperator($operator) as $task) {
            $row = norm_row($task->getAll());
            $iStmt = $pdo->prepare(
                'SELECT display_name, operator, business_no FROM wf_demo_instance WHERE id = :id'
            );
            $iStmt->execute(['id' => $task->getProcessInstanceId()]);
            if ($inst = $iStmt->fetch()) {
                $row['instance_display_name'] = $inst['display_name'];
                $row['instance_operator']     = $inst['operator'];
                $row['business_no']           = $inst['business_no'];
            }
            $rows[] = $row;
        }
        json_out($rows);
    }

    if ($path === '/dones' && $method === 'GET') {
        $operator = trim((string) ($_GET['operator'] ?? ''));
        if ($operator === '') {
            json_out(null, 'operator 必填', 1, 200);
        }
        $stmt = $pdo->prepare(
            'SELECT t.*, i.display_name AS instance_display_name, i.operator AS instance_operator, i.state AS instance_state
             FROM wf_demo_task t
             LEFT JOIN wf_demo_instance i ON i.id = t.process_instance_id
             WHERE t.task_state = 1 AND t.operator = :operator
             ORDER BY t.finish_time DESC, t.create_time DESC LIMIT 200'
        );
        $stmt->execute(['operator' => $operator]);
        json_out(array_map('madong\workflow\demo\norm_row', $stmt->fetchAll()));
    }

    // ---------- 办理任务 ----------
    if (preg_match('#^/tasks/([\w-]+)/complete$#', $path, $m) && $method === 'POST') {
        $taskId   = $m[1];
        $b        = body();
        $operator = (string) ($b['operator'] ?? '');
        $action   = (string) ($b['action'] ?? 'agree');       // agree|reject|back
        $comment  = (string) ($b['comment'] ?? '');
        $vars     = is_array($b['vars'] ?? null) ? $b['vars'] : [];

        if ($operator === '') {
            json_out(null, 'operator 必填', 1, 200);
        }

        $taskService = $engine->getService(IProcessTaskService::class);
        $task = $taskService->getById($taskId);
        if ($task === null) {
            json_out(null, '任务不存在', 1, 404);
        }
        if (!$taskService->isAllowed($task, $operator)) {
            json_out(null, '无权办理该任务', 403, 403);
        }

        $vars[ProcessConst::SUBMIT_TYPE] = $action === 'reject'
            ? SubmitType::REJECT->value
            : SubmitType::AGREE->value;
        $vars['comment'] = $comment;

        $created = match ($action) {
            'reject' => $engine->rejectToEnd($taskId, $operator),                    // 拒绝终止
            'back'   => $engine->rejectToFirst($taskId, $operator, $vars),           // 退回发起节点
            default  => $engine->completeTask($taskId, $operator, $vars),            // 同意流转
        };

        $createdIds = array_map(
            static fn($t) => method_exists($t, 'getId') ? $t->getId() : null,
            $created
        );
        json_out(['created_task_ids' => array_values(array_filter($createdIds))]);
    }

    // ---------- 监控 ----------
    if ($path === '/monitor/overview' && $method === 'GET') {
        $instanceStates = $pdo->query(
            'SELECT state, COUNT(*) AS c FROM wf_demo_instance GROUP BY state'
        )->fetchAll();
        $taskStates = $pdo->query(
            'SELECT task_state, COUNT(*) AS c FROM wf_demo_task GROUP BY task_state'
        )->fetchAll();

        $stmt = $pdo->query(
            'SELECT id, define_name, display_name, operator, state, title, create_time
             FROM wf_demo_instance WHERE state = ' . ProcessState::ACTIVE->value . '
             ORDER BY create_time DESC LIMIT 50'
        );
        json_out([
            'instance_states' => $instanceStates,
            'task_states'     => $taskStates,
            'running'         => array_map('madong\workflow\demo\norm_row', $stmt->fetchAll()),
        ]);
    }

    json_out(null, "未匹配的接口: {$method} {$path}", 1, 404);
} catch (FlowException $e) {
    json_out(null, $e->getMessage(), 1, 200);
} catch (\Throwable $e) {
    json_out(
        ['exception' => $e::class],
        $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(),
        500,
        500
    );
}

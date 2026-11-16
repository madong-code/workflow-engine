<?php
/**
 * workflow demo · REST JSON API 控制器（路由分发 + 响应输出，无框架依赖）
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
 *   GET    /api/workspace/overview          工作台统计（个人卡 + 全局览 + 趋势/Top/分布）
 *   GET    /api/about                       关于页（扩展包元信息 composer.json 权威 + 运行环境）
 *   POST   /api/instances/{id}/cc           抄送实例给指定用户
 *   GET    /api/ccs?operator=               我的抄送列表
 *   GET    /api/delegates                   委托关系列表
 *   POST   /api/delegates                   新建委托 {operator, targetOperator, defineName?}
 *   POST   /api/delegates/{id}/toggle       委托启用/停用
 *   DELETE /api/delegates/{id}              删除委托
 *
 * 统一响应 {code, msg, data}；code=0 成功。业务编排见 app/service/*。
 */

declare(strict_types=1);

namespace madong\workflow\demo\controller;

require_once __DIR__ . '/../bootstrap.php';

use madong\workflow\demo\model\Database;
use madong\workflow\demo\service\{CcService, DefineService, DelegateService, InstanceService, MonitorService, TaskService, UserService, WorkspaceService};
use madong\workflow\exception\FlowException;
use function madong\workflow\demo\{ensureDatabase, engine};

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** 统一 JSON 输出（bigint id 以字符串传输，避免 JS 精度丢失） */
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

/**
 * 关于页数据：扩展包元信息（composer.json 为权威来源，版本以包根 git tag 兜底）+ 运行环境。
 * 前端依赖版本纯前端静态清单无法动态探测，由 about.html 单点维护。
 */
function demo_about(): array
{
    $root = \dirname(__DIR__, 3);   // demo/app/controller 的上三级 = 包根 packages/workflow
    $meta = \is_file($root . '/composer.json')
        ? (\json_decode((string) \file_get_contents($root . '/composer.json'), true) ?: [])
        : [];

    $version = $meta['version'] ?? null;
    if ($version === null || $version === '') {
        // composer.json 未固定版本：包根独立仓库 git tag 兜底（无 git / 非 tag 环境返回 null）
        $describe = @\shell_exec(
            'git -C ' . \escapeshellarg($root) . ' describe --tags 2>'
            . (\DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null')
        );
        $version = \is_string($describe) && \trim($describe) !== '' ? \trim($describe) : null;
    }

    return [
        'package' => [
            'name'        => $meta['name'] ?? 'madong/workflow',
            'description' => $meta['description'] ?? null,
            'keywords'    => $meta['keywords'] ?? [],
            'license'     => $meta['license'] ?? null,
            'version'     => $version,
            'require'     => $meta['require'] ?? [],
            'authors'     => $meta['authors'] ?? [],
        ],
        'environment' => [
            'php'        => PHP_VERSION,
            'sapi'       => PHP_SAPI,
            'os'         => PHP_OS_FAMILY,
            'extensions' => \array_values(\array_filter(
                ['pdo', 'pdo_sqlite', 'json', 'mbstring', 'curl', 'openssl'],
                'extension_loaded'
            )),
        ],
    ];
}

$pdo    = Database::pdo();
$engine = engine();
ensureDatabase();

$defineSvc    = new DefineService($engine, $pdo);
$instanceSvc  = new InstanceService($engine, $pdo);
$ccSvc        = new CcService($pdo);
$delegateSvc  = new DelegateService($pdo);
$taskSvc      = new TaskService($engine, $pdo, $delegateSvc);
$monitorSvc   = new MonitorService($pdo);
$workspaceSvc = new WorkspaceService($pdo, $taskSvc);

try {
    $path   = route();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // ---------- 用户 ----------
    if ($path === '/users' && $method === 'GET') {
        json_out(UserService::list());
    }

    // ---------- 流程定义 ----------
    if ($path === '/defines' && $method === 'GET') {
        json_out($defineSvc->list());
    }

    if ($path === '/defines' && $method === 'POST') {
        json_out($defineSvc->create(body()));
    }

    if (preg_match('#^/defines/([\w-]+)/publish$#', $path, $m) && $method === 'POST') {
        json_out($defineSvc->publish($m[1]));
    }

    if (preg_match('#^/defines/([\w-]+)$#', $path, $m)) {
        $id = $m[1];
        if ($method === 'GET') {
            json_out($defineSvc->detail($id));
        }
        if ($method === 'PUT') {
            json_out($defineSvc->update($id, body()));
        }
        if ($method === 'DELETE') {
            $defineSvc->delete($id);
            json_out(null);
        }
    }

    // ---------- 发起流程 ----------
    if ($path === '/instances' && $method === 'POST') {
        json_out($instanceSvc->start(body()));
    }

    // ---------- 我的申请 ----------
    if ($path === '/instances' && $method === 'GET') {
        $operator = trim((string) ($_GET['operator'] ?? ''));
        $state    = isset($_GET['state']) ? (int) $_GET['state'] : null;
        json_out($instanceSvc->list($operator, $state));
    }

    // ---------- 实例详情（轨迹 + 高亮） ----------
    if (preg_match('#^/instances/([\w-]+)$#', $path, $m) && $method === 'GET') {
        json_out($instanceSvc->detail($m[1]));
    }

    // ---------- 抄送 ----------
    if (preg_match('#^/instances/([\w-]+)/cc$#', $path, $m) && $method === 'POST') {
        json_out($ccSvc->notify($m[1], body()));
    }

    if ($path === '/ccs' && $method === 'GET') {
        json_out($ccSvc->list(trim((string) ($_GET['operator'] ?? ''))));
    }

    // ---------- 委托 ----------
    if ($path === '/delegates' && $method === 'GET') {
        json_out($delegateSvc->list());
    }

    if ($path === '/delegates' && $method === 'POST') {
        json_out($delegateSvc->create(body()));
    }

    if (preg_match('#^/delegates/([\w-]+)/toggle$#', $path, $m) && $method === 'POST') {
        json_out($delegateSvc->toggle($m[1]));
    }

    if (preg_match('#^/delegates/([\w-]+)$#', $path, $m) && $method === 'DELETE') {
        $delegateSvc->delete($m[1]);
        json_out(null);
    }

    // ---------- 待办 / 已办 ----------
    if ($path === '/todos' && $method === 'GET') {
        json_out($taskSvc->todos(trim((string) ($_GET['operator'] ?? ''))));
    }

    if ($path === '/dones' && $method === 'GET') {
        json_out($taskSvc->dones(trim((string) ($_GET['operator'] ?? ''))));
    }

    // ---------- 办理任务 ----------
    if (preg_match('#^/tasks/([\w-]+)/complete$#', $path, $m) && $method === 'POST') {
        json_out($taskSvc->complete($m[1], body()));
    }

    // ---------- 监控 ----------
    if ($path === '/monitor/overview' && $method === 'GET') {
        json_out($monitorSvc->overview());
    }

    // ---------- 工作台 ----------
    if ($path === '/workspace/overview' && $method === 'GET') {
        json_out($workspaceSvc->overview(trim((string) ($_GET['operator'] ?? ''))));
    }

    // ---------- 关于 ----------
    if ($path === '/about' && $method === 'GET') {
        json_out(demo_about());
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

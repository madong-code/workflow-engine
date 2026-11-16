<?php
/**
 * workflow demo · API 全链路冒烟脚本
 *
 * 前置：demo 服务器已启动（php -S 127.0.0.1:8100 -t . demo/public/index.php，
 *       在 packages/workflow 根目录执行）
 *
 * 用法：php demo/tests/smoke.php [baseUrl]
 *       默认 baseUrl = http://127.0.0.1:8100
 *
 * 覆盖场景：
 *   1. 种子数据（用户 / 5 条流程定义）
 *   2. 请假流程 · 短假分支（f_day<3 → 人事审批）
 *   3. 请假流程 · 长假分支（f_day>=3 → 领导审批）
 *   4. 请假流程 · 退回到发起节点（rejectToFirst）后重新提交
 *   5. 报销流程 · 小额直达（f_amount<1000 → 直接结束）
 *   6. 报销流程 · 拒绝终止（rejectToEnd → 实例 REJECTED）
 *   7. 并行分支流程（fork/join 三路并行）
 *   8. 会签流程（ALL 模式，两人会签后流转）
 *   9. 自定义节点流程（金额计算 handler 写入变量）
 *  10. 实例详情（任务轨迹 / 流程图高亮数据）与监控统计
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8100', '/');

$passed = 0;
$failed = 0;

function check(bool $cond, string $msg): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$msg}\n";
    } else {
        $failed++;
        echo "[FAIL] {$msg}\n";
    }
}

/** 调用 demo API，code!=0 或非 JSON 直接抛异常终止 */
function http(string $method, string $path, ?array $body = null): array
{
    global $base;
    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => "Content-Type: application/json\r\n",
            'ignore_errors' => true,
            'timeout'       => 30,
        ],
    ];
    if ($body !== null) {
        $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    $raw = @file_get_contents($base . '/api' . $path, false, stream_context_create($opts));
    if ($raw === false) {
        throw new RuntimeException("请求失败（服务未启动？）: {$method} {$base}/api{$path}");
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("响应非 JSON: {$method} {$path} => " . substr($raw, 0, 200));
    }
    if (($json['code'] ?? null) !== 0) {
        throw new RuntimeException("API 业务失败 {$method} {$path}: " . ($json['msg'] ?? '?'));
    }
    return $json['data'] ?? [];
}

function startInstance(string $defineId, string $operator, array $vars = []): array
{
    return http('POST', '/instances', ['defineId' => $defineId, 'operator' => $operator, 'vars' => $vars]);
}

function completeTask(string $taskId, string $operator, string $action = 'agree', string $comment = ''): array
{
    return http('POST', "/tasks/{$taskId}/complete", [
        'operator' => $operator,
        'action'   => $action,
        'comment'  => $comment,
        'vars'     => [],
    ]);
}

/** 按节点名取待办（不存在则抛异常） */
function todoByName(string $operator, string $taskName): array
{
    $todos = http('GET', '/todos?operator=' . urlencode($operator));
    foreach ($todos as $t) {
        if (($t['task_name'] ?? '') === $taskName) {
            return $t;
        }
    }
    throw new RuntimeException("待办节点 [{$taskName}] 不存在（operator={$operator}）");
}

function detail(string $instanceId): array
{
    return http('GET', '/instances/' . $instanceId);
}

echo "=== workflow demo 冒烟测试 ===\n目标: {$base}\n\n";

// ---------- 1. 种子数据 ----------
$users = http('GET', '/users');
check(count($users) === 5, '种子用户 5 个（实际 ' . count($users) . '）');
$userNames = array_column($users, 'name');
check(in_array('user1', $userNames, true) && in_array('dept_manager', $userNames, true), '演示用户含 user1 / dept_manager');

$defines = http('GET', '/defines');
$defineMap = [];
foreach ($defines as $d) {
    $defineMap[$d['name']] = $d;
}
check(count($defineMap) >= 5, '种子流程定义 >= 5（实际 ' . count($defineMap) . '）');
foreach (['wf-leave', 'wf-expense', 'wf-fork-join', 'wf-countersign', 'wf-custom-node'] as $name) {
    check(isset($defineMap[$name]) && (int) $defineMap[$name]['state'] === 1, "定义 [{$name}] 已发布");
}

// ---------- 2. 请假 · 短假分支 ----------
$inst = startInstance($defineMap['wf-leave']['id'], 'user1', ['f_day' => 2, 'title' => '短假冒烟', 'reason' => '冒烟测试']);
$instanceId = $inst['id'];
check(!empty($instanceId), '请假流程发起成功');

$t = todoByName('user1', 'apply');
check((int) $t['task_state'] === 0, '发起人 [张三] 产生 apply 待办');
completeTask($t['id'], 'user1', 'agree', '提交申请');

$t = todoByName('dept_manager', 'approveDept');
check(!empty($t['id']), '部门经理 [钱七] 产生 approveDept 待办');
completeTask($t['id'], 'dept_manager', 'agree', '同意');

$t = todoByName('hr_manager', 'approveHr');   // f_day=2 < 3 → 人事审批
check(!empty($t['id']), '短假分支命中：人事经理 [李四] 产生 approveHr 待办');
completeTask($t['id'], 'hr_manager', 'agree', '通过');

$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '短假流程办结（实例 state=1 FINISHED）');
check(in_array('approveHr', $d['history_node_names'], true) && !in_array('approveBoss', $d['history_node_names'], true), '轨迹高亮：走过 approveHr、未走 approveBoss');

// ---------- 3. 请假 · 长假分支 ----------
$inst = startInstance($defineMap['wf-leave']['id'], 'user1', ['f_day' => 5, 'title' => '长假冒烟']);
$instanceId = $inst['id'];
completeTask(todoByName('user1', 'apply')['id'], 'user1');
completeTask(todoByName('dept_manager', 'approveDept')['id'], 'dept_manager');
completeTask(todoByName('boss_manager', 'approveBoss')['id'], 'boss_manager', 'agree', '长假审批');   // f_day>=3 → 领导审批
$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '长假流程办结（state=1）');
check(in_array('approveBoss', $d['history_node_names'], true) && !in_array('approveHr', $d['history_node_names'], true), '轨迹高亮：走过 approveBoss、未走 approveHr');

// ---------- 4. 请假 · 退回后重新提交 ----------
$inst = startInstance($defineMap['wf-leave']['id'], 'user1', ['f_day' => 5, 'title' => '退回冒烟']);
$instanceId = $inst['id'];
completeTask(todoByName('user1', 'apply')['id'], 'user1');
completeTask(todoByName('dept_manager', 'approveDept')['id'], 'dept_manager', 'back', '材料不全，退回');
$t = todoByName('user1', 'apply');
check(!empty($t['id']), '退回后发起人重新产生 apply 待办');
completeTask($t['id'], 'user1', 'agree', '补充材料后重新提交');
completeTask(todoByName('dept_manager', 'approveDept')['id'], 'dept_manager', 'agree', '再次通过');
completeTask(todoByName('boss_manager', 'approveBoss')['id'], 'boss_manager');
$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '退回后重新走完流程（state=1）');
check(count(array_filter($d['tasks'], fn($x) => $x['task_name'] === 'apply')) >= 2, 'apply 节点出现两次任务记录（首次 + 退回重提）');

// ---------- 5. 报销 · 小额直达 ----------
$inst = startInstance($defineMap['wf-expense']['id'], 'user1', ['f_amount' => 500, 'title' => '小额报销']);
$instanceId = $inst['id'];
completeTask(todoByName('user1', 'apply')['id'], 'user1');
$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '小额报销（f_amount<1000）审批直达结束（state=1）');

// ---------- 6. 报销 · 拒绝终止 ----------
$inst = startInstance($defineMap['wf-expense']['id'], 'user1', ['f_amount' => 1500, 'title' => '大额报销']);
$instanceId = $inst['id'];
completeTask(todoByName('user1', 'apply')['id'], 'user1');
completeTask(todoByName('dept_manager', 'approveDept')['id'], 'dept_manager', 'reject', '票据不全，拒绝');
$d = detail($instanceId);
check((int) $d['instance']['state'] === 2, '拒绝终止后实例 state=2 REJECTED');
$doing = array_filter($d['tasks'], fn($x) => (int) $x['task_state'] === 0);
check(count($doing) === 0, '拒绝终止后无残留待办任务');

// ---------- 7. 并行分支 ----------
$inst = startInstance($defineMap['wf-fork-join']['id'], 'user1', [
    'title' => '并行冒烟',
    'taskA' => 'dept_manager',
    'taskB' => 'hr_manager',
    'taskC' => 'boss_manager',
]);
$instanceId = $inst['id'];
$d = detail($instanceId);
$doingNames = array_map(fn($x) => $x['task_name'], array_filter($d['tasks'], fn($x) => (int) $x['task_state'] === 0));
check(count($doingNames) === 3, 'fork 后同时产生 3 个并行任务（实际 ' . count($doingNames) . '）');
completeTask(todoByName('dept_manager', 'taskA')['id'], 'dept_manager');
completeTask(todoByName('hr_manager', 'taskB')['id'], 'hr_manager');
completeTask(todoByName('boss_manager', 'taskC')['id'], 'boss_manager');
$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '三路并行全部办结后 join 合流，实例办结（state=1）');

// ---------- 8. 会签 ----------
$inst = startInstance($defineMap['wf-countersign']['id'], 'user1', [
    'title'                    => '会签冒烟',
    'countersign.operatorList' => ['dept_manager', 'hr_manager'],
]);
$instanceId = $inst['id'];
completeTask(todoByName('user1', 'apply')['id'], 'user1');
$d = detail($instanceId);
$doingNames = array_map(fn($x) => $x['task_name'], array_filter($d['tasks'], fn($x) => (int) $x['task_state'] === 0));
check(count(array_keys($doingNames, 'countersign', true)) === 2, '会签节点产生 2 个成员任务');
// 第一人办完不应流转（兄弟任务仍 DOING）
completeTask(todoByName('dept_manager', 'countersign')['id'], 'dept_manager', 'agree', '会签第1票');
$d = detail($instanceId);
$stillDoing = array_filter($d['tasks'], fn($x) => (int) $x['task_state'] === 0 && $x['task_name'] === 'countersign');
check(count($stillDoing) === 1, '会签第 1 票后流程不流转（尚有 1 个 DOING 成员任务）');
check((int) $d['instance']['state'] === 0, '会签未完成时实例仍为 ACTIVE');
// 第二人办完 → 流转到 approveDept
completeTask(todoByName('hr_manager', 'countersign')['id'], 'hr_manager', 'agree', '会签第2票');
completeTask(todoByName('dept_manager', 'approveDept')['id'], 'dept_manager');
$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '会签全部完成后流转并办结（state=1）');
$csVars = $d['instance']['variable_json'] ?? [];
check(isset($csVars['cs_countersign_operatorList']) && count($csVars['cs_countersign_operatorList']) === 2, '会签变量 cs_countersign_operatorList 记录 2 名成员');

// ---------- 9. 自定义节点 ----------
$inst = startInstance($defineMap['wf-custom-node']['id'], 'user1', ['f_amount' => 2000, 'title' => '自定义节点冒烟']);
$instanceId = $inst['id'];
completeTask(todoByName('user1', 'apply')['id'], 'user1');
// custom 节点自动执行（AmountCalculateHandler），无需人工办理
completeTask(todoByName('dept_manager', 'approveDept')['id'], 'dept_manager');
$d = detail($instanceId);
check((int) $d['instance']['state'] === 1, '自定义节点流程办结（state=1）');
$vars = $d['instance']['variable_json'] ?? [];
check(isset($vars['calc_result']) && (string) ($vars['calc_result']['tax_included'] ?? '') !== '', '自定义节点已写入 calc_result 变量（含税总额 tax_included）');

// ---------- 10. 详情与监控 ----------
$d = detail($instanceId);
check(!empty($d['tasks']) && !empty($d['history_node_names']), '实例详情含任务轨迹与高亮节点');

$monitor = http('GET', '/monitor/overview');
$iStates = array_column($monitor['instance_states'], 'c', 'state');
check((int) ($iStates[1] ?? 0) >= 6, '监控统计：已办结实例 >= 6（实际 ' . ($iStates[1] ?? 0) . '）');
check((int) ($iStates[2] ?? 0) >= 1, '监控统计：已拒绝实例 >= 1');
check((int) ($iStates[0] ?? 0) === 0, '监控统计：运行中实例清零（冒烟流程全部办结/拒绝）');

// ---------- 定义 CRUD ----------
$newDefine = http('POST', '/defines', ['name' => 'wf-smoke-crud', 'display_name' => '冒烟CRUD', 'category' => '演示流程']);
check(!empty($newDefine['id']) && (int) $newDefine['state'] === 0, '新建定义（草稿态）');
$updated = http('PUT', '/defines/' . $newDefine['id'], ['content' => ['name' => 'wf-smoke-crud', 'nodes' => [], 'edges' => []]]);
check(is_string($updated['content'] ?? null) && str_contains($updated['content'], 'nodes'), '更新定义 content（JSON 字符串回存）');
http('DELETE', '/defines/' . $newDefine['id']);
$left = http('GET', '/defines');
check(!in_array('wf-smoke-crud', array_column($left, 'name'), true), '删除定义成功');

echo "\n=== 结果: {$passed} 通过, {$failed} 失败 ===\n";
exit($failed > 0 ? 1 : 0);

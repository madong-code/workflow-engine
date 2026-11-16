<?php
/** 临时脚本：清空 demo 表并重新安装种子（表结构保留） */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use madong\workflow\demo\model\Database;

$pdo = Database::pdo();
$tables = [
    'wf_demo_task_actor_history',
    'wf_demo_task_history',
    'wf_demo_instance_history',
    'wf_demo_task_actor',
    'wf_demo_task',
    'wf_demo_instance',
    'wf_demo_cc_instance',
    'wf_demo_delegate',
    'wf_demo_define',
    'wf_demo_user',
];
foreach ($tables as $t) {
    $pdo->exec("TRUNCATE TABLE {$t}");
}
Database::ensureSchema();   // define 表已空 → 重新安装种子（含修正后的 wf-fork-join）
$count = $pdo->query('SELECT COUNT(*) FROM wf_demo_define')->fetchColumn();
echo "defines reinstalled: {$count}\n";

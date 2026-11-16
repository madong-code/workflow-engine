<?php
/**
 * workflow demo · 种子数据安装
 *
 * - 演示用户（对齐 jeeflow 风格的多用户切换，name 即 operator）
 * - 5 条演示流程定义（源自引擎 tests/process 的已验证流程 json，处理人改为
 *   demo 角色 assignee，自定义节点 clazz 指向 demo handler）
 */

declare(strict_types=1);

namespace madong\workflow\demo\model;

use PDO;

final class Seeds
{
    /** @return array<string,string> 演示用户（operator => 显示名） */
    public static function users(): array
    {
        return [
            'user1'           => '张三（普通员工）',
            'dept_manager'    => '钱七（部门经理）',
            'hr_manager'      => '李四（人事经理）',
            'boss_manager'    => '王五（公司领导）',
            'finance_manager' => '赵六（财务经理）',
        ];
    }

    public static function install(PDO $pdo): void
    {
        $now = time();

        // 用户
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO wf_demo_user (id, name, display_name, role, create_time)
             VALUES (:id, :name, :display_name, :role, :create_time)'
        );
        $roles = [
            'user1'           => '发起人',
            'dept_manager'    => '审批人',
            'hr_manager'      => '审批人',
            'boss_manager'    => '审批人',
            'finance_manager' => '审批人',
        ];
        foreach (self::users() as $name => $displayName) {
            $stmt->execute([
                'id'           => Id::next(),
                'name'         => $name,
                'display_name' => $displayName,
                'role'         => $roles[$name] ?? '',
                'create_time'  => $now,
            ]);
        }

        // 流程定义（发布态）
        $seedDir = __DIR__ . '/seeds';
        $files = ['wf-leave.json', 'wf-expense.json', 'wf-fork-join.json', 'wf-countersign.json', 'wf-custom-node.json'];
        $stmt = $pdo->prepare(
            'INSERT INTO wf_demo_define (id, name, display_name, category, state, is_active, version, content, create_time, update_time)
             VALUES (:id, :name, :display_name, :category, :state, :is_active, :version, :content, :create_time, :update_time)'
        );
        foreach ($files as $file) {
            $path = $seedDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $content = (string) file_get_contents($path);
            $json    = json_decode($content, true);
            if (!is_array($json)) {
                continue;
            }
            $stmt->execute([
                'id'           => Id::next(),
                'name'         => (string) ($json['name'] ?? $file),
                'display_name' => (string) ($json['display_name'] ?? $file),
                'category'     => '演示流程',
                'state'        => 1,
                'is_active'    => 1,
                'version'      => 1,
                'content'      => $content,
                'create_time'  => $now,
                'update_time'  => $now,
            ]);
        }
    }
}

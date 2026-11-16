<?php
/**
 * workflow demo · 原生 MySQL (PDO) 连接与表结构初始化
 *
 * - 连接参数来源：环境变量 WF_DEMO_DB_* > demo/config/.env 文件 > 内置默认值
 * - ensureSchema() 幂等：CREATE DATABASE / CREATE TABLE IF NOT EXISTS + 种子数据
 * - 全部为原生 SQL，不依赖任何框架 ORM
 */

declare(strict_types=1);

namespace madong\workflow\demo\model;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    /** 连接参数（host/port/dbname/user/pass/charset） */
    public static function config(): array
    {
        $env = self::loadEnvFile();
        $get = static function (string $key, string $fallback) use ($env): string {
            $candidates = [
                $_SERVER['WF_DEMO_' . $key] ?? null,
                $_ENV['WF_DEMO_' . $key] ?? null,
                getenv('WF_DEMO_' . $key) ?: null,
                $env['WF_DEMO_' . $key] ?? null,
            ];
            foreach ($candidates as $v) {
                if ($v !== null && $v !== '') {
                    return (string) $v;
                }
            }
            return $fallback;
        };

        return [
            'host'    => $get('DB_HOST', '127.0.0.1'),
            'port'    => $get('DB_PORT', '3306'),
            'dbname'  => $get('DB_NAME', 'madong_demo'),
            'user'    => $get('DB_USER', 'root'),
            'pass'    => $get('DB_PASS', 'root'),
            'charset' => 'utf8mb4',
        ];
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = self::config();

        // 先不带库名连接，确保库存在（幂等）
        $serverDsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['charset']
        );
        $server = new PDO($serverDsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $server->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET %s COLLATE utf8mb4_unicode_ci',
            $cfg['dbname'], $cfg['charset']
        ));

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['charset']
        );
        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }

    /** 幂等初始化：建表 + 种子（define 表为空时写入演示流程与用户） */
    public static function ensureSchema(): void
    {
        $pdo = self::pdo();

        $statements = [
            'CREATE TABLE IF NOT EXISTS wf_demo_user (
                id BIGINT UNSIGNED PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT "",
                create_time BIGINT NOT NULL DEFAULT 0,
                UNIQUE KEY uk_user_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_define (
                id BIGINT UNSIGNED PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                category VARCHAR(50) NOT NULL DEFAULT "",
                state TINYINT NOT NULL DEFAULT 0 COMMENT "0草稿 1已发布 2禁用",
                is_active TINYINT NOT NULL DEFAULT 0 COMMENT "是否活跃版本",
                version INT NOT NULL DEFAULT 1,
                content LONGTEXT NULL COMMENT "流程定义 JSON",
                create_time BIGINT NOT NULL DEFAULT 0,
                update_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_define_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_instance (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_define_id BIGINT UNSIGNED NOT NULL,
                define_name VARCHAR(64) NOT NULL DEFAULT "",
                display_name VARCHAR(100) NOT NULL DEFAULT "",
                business_no VARCHAR(64) NULL,
                title VARCHAR(200) NOT NULL DEFAULT "",
                state TINYINT NOT NULL DEFAULT 0 COMMENT "0活动中 1已完成 2已驳回 3挂起 4撤回 5撤销",
                operator VARCHAR(64) NOT NULL,
                variable LONGTEXT NULL COMMENT "流程变量 JSON",
                parent_id BIGINT UNSIGNED NULL,
                parent_node_name VARCHAR(64) NULL,
                create_time BIGINT NOT NULL DEFAULT 0,
                end_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_instance_define (process_define_id),
                KEY idx_instance_operator (operator),
                KEY idx_instance_state (state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_task (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_instance_id BIGINT UNSIGNED NOT NULL,
                task_name VARCHAR(64) NOT NULL,
                display_name VARCHAR(100) NOT NULL DEFAULT "",
                task_state TINYINT NOT NULL DEFAULT 0 COMMENT "0执行中 1完成 2取消 3保留 4协办中",
                task_type TINYINT NOT NULL DEFAULT 0,
                perform_type TINYINT NOT NULL DEFAULT 0,
                operator VARCHAR(64) NULL,
                form_key VARCHAR(64) NULL,
                submit_type VARCHAR(20) NULL,
                comment VARCHAR(500) NULL,
                create_time BIGINT NOT NULL DEFAULT 0,
                finish_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_task_instance (process_instance_id),
                KEY idx_task_operator_state (operator, task_state),
                KEY idx_task_name (task_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_task_actor (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_task_id BIGINT UNSIGNED NOT NULL,
                process_instance_id BIGINT UNSIGNED NOT NULL,
                actor_id VARCHAR(64) NOT NULL,
                actor_type TINYINT NOT NULL DEFAULT 0,
                create_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_actor_task (process_task_id),
                KEY idx_actor_actor (actor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_instance_history (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_instance_id BIGINT UNSIGNED NOT NULL,
                process_define_id BIGINT UNSIGNED NOT NULL,
                define_name VARCHAR(64) NOT NULL DEFAULT "",
                display_name VARCHAR(100) NOT NULL DEFAULT "",
                state TINYINT NOT NULL DEFAULT 0,
                operator VARCHAR(64) NOT NULL,
                create_time BIGINT NOT NULL DEFAULT 0,
                end_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_ih_instance (process_instance_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_task_history (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_task_id BIGINT UNSIGNED NOT NULL,
                process_instance_id BIGINT UNSIGNED NOT NULL,
                task_name VARCHAR(64) NOT NULL,
                display_name VARCHAR(100) NOT NULL DEFAULT "",
                task_state TINYINT NOT NULL DEFAULT 0,
                submit_type VARCHAR(20) NULL,
                comment VARCHAR(500) NULL,
                operator VARCHAR(64) NULL,
                create_time BIGINT NOT NULL DEFAULT 0,
                finish_time BIGINT NOT NULL DEFAULT 0,
                end_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_th_task (process_task_id),
                KEY idx_th_instance (process_instance_id),
                KEY idx_th_operator (operator)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_task_actor_history (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_task_id BIGINT UNSIGNED NOT NULL,
                process_instance_id BIGINT UNSIGNED NOT NULL,
                actor_id VARCHAR(64) NOT NULL,
                actor_type TINYINT NOT NULL DEFAULT 0,
                create_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_ah_task (process_task_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_cc_instance (
                id BIGINT UNSIGNED PRIMARY KEY,
                process_instance_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL DEFAULT "" COMMENT "抄送时刻实例标题快照",
                define_name VARCHAR(64) NOT NULL DEFAULT "" COMMENT "流程定义名",
                instance_operator VARCHAR(64) NOT NULL DEFAULT "" COMMENT "实例发起人",
                cc_operator VARCHAR(64) NOT NULL COMMENT "被抄送人",
                create_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_cc_operator (cc_operator),
                KEY idx_cc_instance (process_instance_id),
                UNIQUE KEY uk_cc_once (process_instance_id, cc_operator)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',

            'CREATE TABLE IF NOT EXISTS wf_demo_delegate (
                id BIGINT UNSIGNED PRIMARY KEY,
                source_operator VARCHAR(64) NOT NULL COMMENT "委托人",
                target_operator VARCHAR(64) NOT NULL COMMENT "受托人",
                define_name VARCHAR(64) NOT NULL DEFAULT "" COMMENT "限定流程名，空=全部流程",
                status TINYINT NOT NULL DEFAULT 1 COMMENT "1生效 0停用",
                create_time BIGINT NOT NULL DEFAULT 0,
                KEY idx_del_target (target_operator, status),
                KEY idx_del_source (source_operator)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }

        // 种子：仅在 define 表为空时写入（幂等）
        $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM wf_demo_define')->fetch()['c'];
        if ($count === 0) {
            Seeds::install($pdo);
        }
    }

    /** @return array<string,string> */
    private static function loadEnvFile(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $file = __DIR__ . '/../../config/.env';
        if (!is_file($file)) {
            return $cache;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $cache[trim($k)] = trim($v, " \t\"'");
        }
        return $cache;
    }
}

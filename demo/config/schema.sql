-- =====================================================================
-- madong/workflow demo · 表结构（手动导入入口）
--
-- 说明：
--   1. 运行 demo 时无需手动导入——首个请求会自动执行幂等建库建表
--      （demo/app/model/Database.php ensureSchema()）；本文件供需要手工
--      初始化 / 审阅表结构的场景使用。
--   2. 种子数据（5 个演示流程定义 + 5 个演示用户）由 demo/app/model/Seeds.php
--      在首个请求时自动写入，不随本文件导入。
--   3. 连接参数可用 demo/config/.env 或 WF_DEMO_DB_* 环境变量覆盖，
--      默认 127.0.0.1:3306 / root / root / madong_demo。
--
-- 手动导入：
--   mysql -h127.0.0.1 -uroot -proot < demo/config/schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `madong_demo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `madong_demo`;

-- 演示用户（operator 即身份标识，jeeflow 风格多用户切换）
CREATE TABLE IF NOT EXISTS `wf_demo_user` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `name` VARCHAR(64) NOT NULL COMMENT '用户标识（operator）',
    `display_name` VARCHAR(100) NOT NULL COMMENT '显示名',
    `role` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '演示角色',
    `create_time` BIGINT NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_user_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='demo 演示用户';

-- 流程定义
CREATE TABLE IF NOT EXISTS `wf_demo_define` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `name` VARCHAR(64) NOT NULL COMMENT '流程标识',
    `display_name` VARCHAR(100) NOT NULL COMMENT '流程名称',
    `category` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '分类',
    `state` TINYINT NOT NULL DEFAULT 0 COMMENT '0草稿 1已发布 2禁用',
    `is_active` TINYINT NOT NULL DEFAULT 0 COMMENT '是否活动版本（同名唯一）',
    `version` INT NOT NULL DEFAULT 1 COMMENT '版本号',
    `content` LONGTEXT NULL COMMENT '流程定义 JSON（visual-flow FDConfigData）',
    `create_time` BIGINT NOT NULL DEFAULT 0,
    `update_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_define_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流程定义';

-- 流程实例
CREATE TABLE IF NOT EXISTS `wf_demo_instance` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_define_id` BIGINT UNSIGNED NOT NULL,
    `define_name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '定义标识冗余',
    `display_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '定义名称冗余',
    `business_no` VARCHAR(64) NULL COMMENT '业务编号',
    `title` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '实例标题',
    `state` TINYINT NOT NULL DEFAULT 0 COMMENT '0活动中 1已完成 2已驳回 3挂起 4撤回 5撤销',
    `operator` VARCHAR(64) NOT NULL COMMENT '发起人',
    `variable` LONGTEXT NULL COMMENT '流程变量 JSON',
    `parent_id` BIGINT UNSIGNED NULL COMMENT '父实例（子流程）',
    `parent_node_name` VARCHAR(64) NULL COMMENT '父节点名（子流程）',
    `create_time` BIGINT NOT NULL DEFAULT 0,
    `end_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_instance_define` (`process_define_id`),
    KEY `idx_instance_operator` (`operator`),
    KEY `idx_instance_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流程实例';

-- 流程任务（运行时）
CREATE TABLE IF NOT EXISTS `wf_demo_task` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_instance_id` BIGINT UNSIGNED NOT NULL,
    `task_name` VARCHAR(64) NOT NULL COMMENT '节点编码',
    `display_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '节点名称',
    `task_state` TINYINT NOT NULL DEFAULT 0 COMMENT '0执行中 1完成 2取消 3保留 4挂起',
    `task_type` TINYINT NOT NULL DEFAULT 0 COMMENT '任务类型（0主办）',
    `perform_type` TINYINT NOT NULL DEFAULT 0 COMMENT '参与类型（0普通 1会签）',
    `operator` VARCHAR(64) NULL COMMENT '主处理人',
    `form_key` VARCHAR(64) NULL COMMENT '表单标识',
    `submit_type` VARCHAR(20) NULL COMMENT '提交类型 agree/reject',
    `comment` VARCHAR(500) NULL COMMENT '审批意见',
    `create_time` BIGINT NOT NULL DEFAULT 0,
    `finish_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_task_instance` (`process_instance_id`),
    KEY `idx_task_operator_state` (`operator`, `task_state`),
    KEY `idx_task_name` (`task_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流程任务';

-- 任务参与人（一任务多处理人，各一条）
CREATE TABLE IF NOT EXISTS `wf_demo_task_actor` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_task_id` BIGINT UNSIGNED NOT NULL,
    `process_instance_id` BIGINT UNSIGNED NOT NULL,
    `actor_id` VARCHAR(64) NOT NULL COMMENT '参与人标识',
    `actor_type` TINYINT NOT NULL DEFAULT 0,
    `create_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_actor_task` (`process_task_id`),
    KEY `idx_actor_actor` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务参与人';

-- 实例历史（归档镜像）
CREATE TABLE IF NOT EXISTS `wf_demo_instance_history` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_instance_id` BIGINT UNSIGNED NOT NULL,
    `process_define_id` BIGINT UNSIGNED NOT NULL,
    `define_name` VARCHAR(64) NOT NULL DEFAULT '',
    `display_name` VARCHAR(100) NOT NULL DEFAULT '',
    `state` TINYINT NOT NULL DEFAULT 0,
    `operator` VARCHAR(64) NOT NULL,
    `create_time` BIGINT NOT NULL DEFAULT 0,
    `end_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_ih_instance` (`process_instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流程实例历史';

-- 任务历史（创建时落 DOING，完结时同步状态）
CREATE TABLE IF NOT EXISTS `wf_demo_task_history` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_task_id` BIGINT UNSIGNED NOT NULL,
    `process_instance_id` BIGINT UNSIGNED NOT NULL,
    `task_name` VARCHAR(64) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL DEFAULT '',
    `task_state` TINYINT NOT NULL DEFAULT 0,
    `submit_type` VARCHAR(20) NULL,
    `comment` VARCHAR(500) NULL,
    `operator` VARCHAR(64) NULL,
    `create_time` BIGINT NOT NULL DEFAULT 0,
    `finish_time` BIGINT NOT NULL DEFAULT 0,
    `end_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_th_task` (`process_task_id`),
    KEY `idx_th_instance` (`process_instance_id`),
    KEY `idx_th_operator` (`operator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流程任务历史';

-- 任务参与人历史
CREATE TABLE IF NOT EXISTS `wf_demo_task_actor_history` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_task_id` BIGINT UNSIGNED NOT NULL,
    `process_instance_id` BIGINT UNSIGNED NOT NULL,
    `actor_id` VARCHAR(64) NOT NULL,
    `actor_type` TINYINT NOT NULL DEFAULT 0,
    `create_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_ah_task` (`process_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务参与人历史';

-- 抄送记录（标题/发起人按抄送时刻快照；同实例同人幂等）
CREATE TABLE IF NOT EXISTS `wf_demo_cc_instance` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `process_instance_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '抄送时刻实例标题快照',
    `define_name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '流程定义名',
    `instance_operator` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '实例发起人',
    `cc_operator` VARCHAR(64) NOT NULL COMMENT '被抄送人',
    `create_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_cc_operator` (`cc_operator`),
    KEY `idx_cc_instance` (`process_instance_id`),
    UNIQUE KEY `uk_cc_once` (`process_instance_id`, `cc_operator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='实例抄送记录';

-- 审批委托（生效中委托使受托人可见并代办委托人待办，act_as）
CREATE TABLE IF NOT EXISTS `wf_demo_delegate` (
    `id` BIGINT UNSIGNED PRIMARY KEY,
    `source_operator` VARCHAR(64) NOT NULL COMMENT '委托人',
    `target_operator` VARCHAR(64) NOT NULL COMMENT '受托人',
    `define_name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '限定流程名，空=全部流程',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1生效 0停用',
    `create_time` BIGINT NOT NULL DEFAULT 0,
    KEY `idx_del_target` (`target_operator`, `status`),
    KEY `idx_del_source` (`source_operator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='审批委托关系';

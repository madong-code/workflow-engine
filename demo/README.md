# madong/workflow demo

`madong/workflow` 引擎的可运行示例：**PDO + 原生 SQL** 存储层 + **原生 PHP** REST API + **iframe 嵌 visual-flow 设计器** 的静态宿主页，零框架依赖（不引入任何 ORM / Web 框架 / 前端构建链）。

组织方式对标 `packages/stepcore/demo/`，页面布局对标 `backend/plugin/workflow` 前端与 [jeeflow 演示](https://jeeflow-demo.mldong.com/)。

## 功能总览

| 页签 | 能力 |
|------|------|
| 流程设计 | 定义列表（新建/删除/发布/版本）、visual-flow 画布/钉钉双模式设计器、保存回传、发布生效 |
| 发起流程 | 已发布定义卡片、按流程预置变量表单（天数/金额/处理人/会签成员）、一键发起 |
| 待办事项 | 按 operator 查询待办，同意 / 退回发起节点 / 拒绝终止，附审批意见 |
| 已办事项 | 办理记录（提交类型、意见、办理时间、实例状态） |
| 我的申请 | 实例列表与状态徽标；详情弹层含任务轨迹时间线 + viewer 流程图（已走节点高亮） |
| 流程监控 | 实例/任务状态统计 + 运行中实例列表 |

内置 5 条种子流程（源自引擎 `tests/process` 已验证流程）：

| 流程 | 演示点 |
|------|--------|
| 请假流程 `wf-leave` | decision 条件分支（`#f_day<3` 人事 / `>=3` 领导）、退回重提 |
| 报销流程 `wf-expense` | 金额分支（小额直达结束 / 大额三级审批）、拒绝终止 |
| 并行分支流程 `wf-fork-join` | fork/join 三路并行、全部办结后合流 |
| 会签流程 `wf-countersign` | ALL 会签（全员办结才流转）、`cs_*` 会签变量落库 |
| 自定义节点流程 `wf-custom-node` | `ingenious:custom` 节点调用 demo handler 写入流程变量 |

演示用户（顶栏切换，operator 即身份）：张三 `user1`（发起人）、钱七 `dept_manager`、李四 `hr_manager`、王五 `boss_manager`、赵六 `finance_manager`。

## 前置条件

- PHP >= 8.1（需 `pdo_mysql` 扩展）
- MySQL 5.7+ / 8.0
- 无需 composer install / node / 构建工具

## 数据库初始化（两种方式任选）

**方式 A：自动（推荐）**——不做任何事。首个请求触发 `Database::ensureSchema()`：幂等建库建表 + 写入种子数据。

**方式 B：手动导入表结构**——种子数据仍由首个请求自动写入：

```shell
mysql -h127.0.0.1 -uroot -proot < demo/src/schema.sql
```

连接参数默认 `127.0.0.1:3306 / root / root / 库名 madong_demo`，可用环境变量或 `demo/.env` 覆盖：

```ini
WF_DEMO_DB_HOST=127.0.0.1
WF_DEMO_DB_PORT=3306
WF_DEMO_DB_NAME=madong_demo
WF_DEMO_DB_USER=root
WF_DEMO_DB_PASS=root
```

## 运行

```shell
# 在 packages/workflow 包根目录执行（-t 的文档根 = 包根）
php -S 127.0.0.1:8100 -t . demo/public/index.php
```

浏览器打开 <http://127.0.0.1:8100/> 即宿主页。`/ui/index.html` 为可独立访问的设计器站点。

php-fpm + nginx 部署见 `demo/nginx.conf.example`（root 指向包根，动态请求转发 `demo/public/index.php`）。

## 全链路冒烟

```shell
# 服务启动后执行（另一终端）
php demo/tests/smoke.php [baseUrl]
```

38 条断言覆盖：种子数据、5 条流程发起与办结、条件分支、退回重提、拒绝终止、并行合流、会签两票流转、自定义节点变量写入、轨迹与高亮、监控统计、定义 CRUD。另有 `demo/tests/reset-db.php` 用于清库重装种子。

## 后端接入（composer path）

`backend/composer.json`（与 `madong/stepcore` 同款接法）：

```json
"require": { "madong/workflow": "@dev" },
"repositories": [
    { "type": "path", "url": "../packages/workflow", "options": { "symlink": true } }
]
```

```shell
composer update madong/workflow --working-dir=backend
```

之后 `backend/vendor/madong/workflow` 即指向本包的链接（Windows junction / POSIX symlink），生产插件 `plugin/workflow` 与 demo 共用同一份引擎源码。

> 引擎 `Container`（src/container/Container.php）在 php-di 缺失时自动降级为数组容器：composer 接入场景行为不变；demo 纯 PSR-4 引入场景无需 php-di。

## 目录结构

```
packages/workflow/
├── src/                      # 引擎源码（克隆自 workflow-engine 仓库）
│   └── container/Container.php   # php-di 缺失时降级为数组容器（demo 无 composer 场景）
├── ui/                       # 设计器静态站点（零联网，可被任意宿主 iframe 远程嵌入）
│   ├── index.html            # window.vue 桥接 + postMessage 协议
│   └── vendor/
│       ├── vue.global.prod.js        # Vue 3 全局构建（拷自 template/admin/node_modules）
│       ├── visual-flow.umd.cjs       # visual-flow UMD 产物（LogicFlow 已打包）
│       └── style.css                 # visual-flow 样式（含主题变量）
└── demo/
    ├── public/index.php      # 前端控制器：/ → 宿主页；/api/* → api.php；静态文件安全 readfile（禁 .php 直出）
    ├── src/
    │   ├── bootstrap.php     # PSR-4 手动 autoload（引擎 + demo）+ engine() 装配工厂 + ensureDatabase()
    │   ├── Database.php      # PDO 连接（WF_DEMO_DB_* / demo/.env）+ 幂等建表 + 种子触发
    │   ├── schema.sql        # 表结构手动导入入口
    │   ├── Id.php            # 雪花 ID（毫秒<<12|序列，字符串传输）
    │   ├── RowDict.php       # DB 行 → 引擎 I* 模型接口实现（对齐 MemoryDict）
    │   ├── Seeds.php         # 种子：5 用户 + 5 流程定义（发布态）
    │   ├── api.php           # REST JSON（统一 {code,msg,data}，CORS，JSON_UNESCAPED_UNICODE）
    │   ├── handler/AmountCalculateHandler.php   # ingenious:custom 节点扩展点示例
    │   ├── store/            # 7 个核心服务 PDO 实现（行为对照 tests/fixture/memory/*）
    │   │   ├── PdoProcessDefineService.php
    │   │   ├── PdoProcessInstanceService.php
    │   │   ├── PdoProcessTaskService.php        # createTask 事务写 task+actors+history；会签变量落库
    │   │   ├── PdoProcessTaskActorService.php
    │   │   ├── PdoProcessInstanceHistoryService.php
    │   │   ├── PdoProcessTaskHistoryService.php
    │   │   └── PdoProcessTaskActorHistoryService.php
    │   └── seeds/            # 5 条流程定义 JSON（visual-flow FDConfigData，ingenious:* 节点规范）
    ├── views/host.php        # 宿主页（6 页签 + 用户切换，iframe 嵌设计器）
    ├── tests/
    │   ├── smoke.php         # 38 断言全链路冒烟（UTF-8 输出）
    │   └── reset-db.php      # 清库重装种子
    ├── nginx.conf.example
    └── README.md
```

## REST API

统一响应 `{code, msg, data}`，`code=0` 成功；id 以字符串传输（避免 bigint 精度丢失）。

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/users` | 演示用户列表 |
| GET | `/api/defines` | 定义列表（不含 content） |
| GET | `/api/defines/{id}` | 定义详情（含 `content_json`） |
| POST | `/api/defines` | 新建（name / display_name / category / content） |
| PUT | `/api/defines/{id}` | 更新（display_name / category / content） |
| DELETE | `/api/defines/{id}` | 删除 |
| POST | `/api/defines/{id}/publish` | 发布（同名其他版本退位） |
| POST | `/api/instances` | 发起：`{defineId, operator, vars}`；`vars.operator` 自动注入 |
| GET | `/api/instances?operator=&state=` | 我的申请（附 `active_nodes`） |
| GET | `/api/instances/{id}` | 详情：instance + tasks（含 actors）+ `history_node_names` + `active_node_names` |
| GET | `/api/todos?operator=` | 待办（operator 直属或 actor 表命中） |
| GET | `/api/dones?operator=` | 已办 |
| POST | `/api/tasks/{id}/complete` | 办理：`{operator, action, comment, vars}`，action = `agree`（流转）/ `back`（退回发起节点）/ `reject`（终止） |
| GET | `/api/monitor/overview` | 实例/任务状态统计 + 运行中实例 |

## 设计器嵌入协议（iframe + postMessage）

设计器站点 `ui/index.html` 可被任意 origin 的宿主 iframe 远程嵌入。URL 参数：

| 参数 | 说明 |
|------|------|
| `mode` | `canvas`（LogicFlow 画布，默认）或 `dingtalk`（钉钉风格树形） |
| `viewer` | `1` = 查看模式（隐藏拖拽面板，用于预览/高亮回放） |
| `targetOrigin` | 覆盖 postMessage 目标 origin（默认 `*`），亦可用 `window.DESIGNER_TARGET_ORIGIN` |

消息协议（对齐 stepcore demo）：

```
iframe → 宿主
  {type: 'flow.ready'}                        # 设计器就绪（宿主收到后才能 flow.load）
  {type: 'flow.save', data: <FDConfigData>}   # 画布控制面板"保存"按钮

宿主 → iframe
  {type: 'flow.load', data: <FDConfigData>, mode?, viewer?, highLight?}
  {type: 'flow.highlight', data: <FDHighLightType>}   # {history_node_names, active_node_names, ...}
```

> 接入要点：`window.vue = window.Vue` 必须在 `visual-flow.umd.cjs` **之前**执行（UMD 工厂签名 `factory(global2.FlowDesigner = {}, global2.vue)`）；插件注册用 `app.use(window.FlowDesigner.default)`（UMD 导出为命名空间对象，Vue 插件在 `default` 上）。

## 引擎服务对接要点（demo 实现对照）

- **必注入 7 服务**：Define / Instance / Task / TaskActor / InstanceHistory / TaskHistory / TaskActorHistory；可选服务引擎按需判空
- **createTask 事务语义**（对齐生产插件）：任务行 + 参与人（每 operator 一条 actor）+ DOING 历史同事务写入；`finishProcessTask` 时同步更新历史
- **assignee 解析**：`${var}` → 完整键 → 点号尾段 → 逗号分隔；`apply.operator` 尾段即 `operator`（发起人变量）；fork-join 分支用 `taskA` 等完整变量键直接配人
- **会签**：`createCountersignTask` 写 `cs_{node}_operatorList` / `cs_{node}_nrOfCompleted` 变量并落库；`getCosignGroupState` 四态（NONE/WAIT/RESUME/FLOW）
- **自定义节点**：`ingenious:custom` 的 `clazz`（点号类名）由引擎实例化并调用 `handle($ctx, $args)`，返回值写入 `var` 指定变量并持久化

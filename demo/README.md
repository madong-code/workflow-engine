# madong/workflow demo

`madong/workflow` 引擎的可运行示例：**PDO + 原生 SQL** 存储层 + **原生 PHP** REST API + **iframe 嵌 visual-flow 设计器**，零构建链依赖（不引入任何 ORM / Web 框架 / 前端构建工具；UI 层本地化引入 Bootstrap 5.3 CSS/JS 作美化基建，主题变量由 common.css 对齐 jeeflow 视觉）。

组织方式对标 `packages/stepcore/demo/`（app/{controller,service,engine,model,views} + public 分层，后端模版一体应用），页面布局与交互对标 [jeeflow 演示](https://jeeflow-demo.mldong.com/)（顶栏 + 左侧菜单 + 单页内容区、右侧抽屉详情、顶部居中 message 提示，用户头像下拉切换）。

## 功能总览

| 菜单 | 能力 |
|------|------|
| 工作台 | 默认首页：个人卡（待办/在办/发起/抄送）+ 全局览 8 卡 + 近 7 天趋势 + 流程 Top 5 + 状态分布 + 最近待办 |
| 发起申请 | 已发布定义卡片、按流程预置变量表单（天数/金额/处理人/会签成员）、一键发起 |
| 我的待办 | 按 operator 查询待办（含受托代办的委托人待办，带「受托自」徽标）；同意 / 退回发起节点 / 拒绝终止 + 详情抽屉 |
| 我发起的 | 实例列表与状态徽标；详情抽屉（Tabs：详情/流程图/审批记录）|
| 我的已办 | 办理记录（提交类型、意见、办理时间、实例状态，含受托代办记录）；详情抽屉 |
| 我的抄送 | 被抄送实例列表；详情抽屉；实例详情内可发起抄送他人 |
| 流程定义 | 定义表格化管理：新建（弹窗）/ 设计（跳设计页自动选中）/ 发布 / 预览 / 删除，搜索 + 分页 |
| 流程设计 | 定义列表（新建/删除/发布/版本）、visual-flow 画布/钉钉双模式设计器、保存回传、发布生效 |
| 我的委托 | 委托管理：我委托的（启停/删除）/ 委托给我的；生效委托使受托人可见并代办委托人待办（act_as） |
| 流程监控 | 实例/任务状态统计 + 运行中实例列表；详情抽屉 |

详情抽屉（`detail-drawer.js`）三页签：**详情**（实例元信息 + 申请信息字段网格 + 抄送按钮）、**流程图**（viewer 高亮 + 钉钉/画布模式切换，首次切到该页签才重建 iframe）、**审批记录**（任务轨迹时间线）；列表页统一调 `DetailDrawer.openInstance(id)`。

所有列表页均带搜索过滤与前端分页（公共分页条 `App.mountPager`，页码切换 + 每页条数）。

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
mysql -h127.0.0.1 -uroot -proot < demo/config/schema.sql
```

连接参数默认 `127.0.0.1:3306 / root / root / 库名 madong_demo`，可用环境变量或 `demo/config/.env` 覆盖（模板见 `demo/config/.env.example`，复制为 `demo/config/.env` 即可生效）：

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

浏览器打开 <http://127.0.0.1:8100/> 即宿主页。`/assets/ui/index.html` 为可独立访问的设计器站点。

约定路由（`demo/public/index.php` 前端控制器）：

| 路径 | 响应 |
|------|------|
| `/` | 宿主壳 `app/views/layout.html`（顶栏 + 左侧菜单 + 按需加载栏目） |
| `/views/{name}.html` | 栏目页片段 `app/views/{name}.html`（layout fetch 注入内容区） |
| `/assets/*` | demo 静态资源 `demo/public/assets/*`（含设计器站点 `/assets/ui/`；realpath 防路径穿越，禁 .php 直出） |
| `/api/*` | REST 接口 `app/controller/api.php` |

php-fpm + nginx 部署见 `demo/config/nginx.conf.example`（root 指向包根，动态请求转发 `demo/public/index.php`，`/assets/` 由 nginx alias 直接托管）。

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
├── src/                          # 引擎源码（PSR-4: madong\workflow；demo 仅通过 bootstrap autoload 引用，边界清晰）
│   └── container/Container.php   # php-di 缺失时降级为数组容器（demo 无 composer 场景）
└── demo/                         # demo 全部资产收敛于此（页面/静态资源/配置/文档/测试）
    ├── app/
    │   ├── bootstrap.php         # PSR-4 分段 autoload（controller/service/engine/model → app/，引擎 → ../src/）+ ensureDatabase()
    │   ├── controller/
    │   │   └── api.php           # 请求分发与入口编排（路由 → service，统一 {code,msg,data} + CORS）
    │   ├── service/              # 业务编排（构造注入 Engine + PDO）
    │   │   ├── UserService.php
    │   │   ├── DefineService.php
    │   │   ├── InstanceService.php   # 发起 / 我的申请 / 详情轨迹与高亮组装（含非任务节点经过推断）
    │   │   ├── TaskService.php       # 待办 / 已办（合并生效委托数据）/ 办理（agree|back|reject + 受托 act_as）
    │   │   ├── CcService.php         # 抄送：实例抄送他人（幂等）+ 我的抄送列表
    │   │   ├── DelegateService.php   # 委托：新建/启停/删除 + sourcesOf（受托可见性）
    │   │   ├── MonitorService.php
    │   │   └── WorkspaceService.php  # 工作台：个人卡 + 全局览 + 趋势/Top/分布 + 最近待办
    │   ├── engine/
    │   │   ├── EngineFactory.php         # 引擎装配工厂（7 个 store 服务注册）
    │   │   └── AmountCalculateHandler.php   # ingenious:custom 节点扩展点示例
    │   ├── model/                # 数据访问层
    │   │   ├── Database.php      # PDO 连接（WF_DEMO_DB_* / demo/config/.env）+ 幂等建表 + 种子触发
    │   │   ├── Id.php            # 雪花 ID（毫秒<<12|序列，字符串传输）
    │   │   ├── RowDict.php       # DB 行 → 引擎 I* 模型接口实现（对齐 MemoryDict）
    │   │   ├── Seeds.php         # 种子：5 用户 + 5 流程定义（发布态）
    │   │   ├── store/            # 7 个核心服务 PDO 实现（行为对照 tests/fixture/memory/*）
    │   │   │   ├── PdoProcessDefineService.php
    │   │   │   ├── PdoProcessInstanceService.php
    │   │   │   ├── PdoProcessTaskService.php        # createTask 事务写 task+actors+history；会签变量落库
    │   │   │   ├── PdoProcessTaskActorService.php
    │   │   │   ├── PdoProcessInstanceHistoryService.php
    │   │   │   ├── PdoProcessTaskHistoryService.php
    │   │   │   └── PdoProcessTaskActorHistoryService.php
    │   │   └── seeds/            # 5 条流程定义 JSON（visual-flow FDConfigData，ingenious:* 节点规范）
    │   └── views/                # 每栏目独立 HTML（layout fetch 注入；公共资源引 public/assets，禁止复制粘贴）
    │       ├── layout.html       # 宿主壳：顶栏 + 左侧菜单（10 栏目）+ 内容区容器 + 用户切换
    │       ├── workspace.html    # 工作台（默认首页）
    │       ├── launch.html       # 发起申请
    │       ├── todo.html         # 我的待办（含详情抽屉 + 受托徽标）
    │       ├── mine.html         # 我发起的
    │       ├── done.html         # 我的已办
    │       ├── cc.html           # 我的抄送
    │       ├── definitions.html  # 流程定义（表格化管理）
    │       ├── designer.html     # 流程设计（接收 definitions 页「设计」跳转自动选中）
    │       ├── delegate.html     # 我的委托（双表 + 新建委托弹窗）
    │       └── monitor.html      # 流程监控
    ├── public/
    │   ├── index.php             # 前端控制器：约定路由 + 静态资源安全 readfile（禁 .php 直出）
    │   ├── assets/
    │   │   ├── common.css        # 公共样式（jeeflow 视觉 + Bootstrap 变量覆盖：顶栏/菜单/表格/徽标/分页/抽屉Tabs/工作台/message）
    │   │   ├── app.js            # App 模块：api()/message/badge/fmtTime/用户切换/公共分页 mountPager/栏目注册与按需加载
    │   │   ├── form-templates.js # 发起表单模板共享（launch 发起表单 + 详情抽屉申请信息展示）
    │   │   ├── detail-drawer.js  # 实例详情共享右侧抽屉（Tabs 详情/流程图/审批记录 + 抄送 + viewer 高亮 + 模式切换）
    │   │   ├── vendor/bootstrap/ # Bootstrap 5.3 本地化（min.css + bundle.min.js，零联网）
    │   │   └── ui/               # 设计器静态站点（原包根 ui/ 迁入；零联网，可被任意宿主 iframe 嵌入）
    │   │       ├── index.html    # window.vue 桥接 + postMessage 协议
    │   │       └── vendor/
    │   │           ├── vue.global.prod.js        # Vue 3 全局构建
    │   │           ├── visual-flow.umd.cjs       # visual-flow UMD 产物（LogicFlow 已打包）
    │   │           └── style.css                 # visual-flow 样式（含主题变量）
    ├── config/
    │   ├── .env.example          # 数据库连接配置模板（复制为 .env 或用 WF_DEMO_DB_* 环境变量）
    │   ├── schema.sql            # 表结构手动导入入口
    │   └── nginx.conf.example    # php-fpm + nginx 部署示例
    ├── tests/
    │   ├── smoke.php             # 38 断言全链路冒烟（UTF-8 输出）
    │   └── reset-db.php          # 清库重装种子
    └── README.md
```

## views 栏目一览

| 文件 | 栏目 | 主要内容与交互 |
|------|------|----------------|
| `layout.html` | 宿主壳 | 顶栏（logo / 标题 / 用户下拉切换：姓名+身份+账号码，当前账号高亮）+ 左侧 200px 菜单 10 项 + 内容区容器；按需 fetch 栏目页注入，公共资源引 `/assets/*` |
| `workspace.html` | 工作台 | 默认首页。个人卡（待办/在办/发起/抄送）+ 全局览 8 卡 + 近 7 天趋势双柱图 + 流程 Top 5 + 状态分布横条 + 最近待办（空态「暂无待办，喝茶吧 ☕」） |
| `launch.html` | 发起申请 | 已发布定义卡片；点击弹居中发起表单（`FormTemplates.fieldsFor` 按流程预置字段：文本 / 数字 / 下拉 / 多选 / 文本域），提交 `POST /instances` |
| `todo.html` | 我的待办 | 待办表格（含受托代办「受托自」徽标）+ 搜索 + 分页；详情抽屉 + 同意 / 退回 / 拒绝弹办理弹窗，提交 `POST /tasks/{id}/complete` |
| `mine.html` | 我发起的 | 实例表格（状态徽标 / 当前节点）+ 搜索 + 分页；详情抽屉 |
| `done.html` | 我的已办 | 已办表格（提交类型 / 意见 / 办理时间 / 状态徽标，含受托代办）+ 搜索 + 分页；详情抽屉 |
| `cc.html` | 我的抄送 | 被抄送实例表格 + 搜索 + 分页；详情抽屉 |
| `definitions.html` | 流程定义 | 定义表格（状态/版本徽标）+ 搜索 + 分页；新建弹窗 / 设计跳转 / 发布 / 预览 / 删除 |
| `designer.html` | 流程设计 | 定义列表（新建 / 发布 / 徽标）+ 设计器 iframe（画布 / 钉钉双模式）；`flow.ready` → 载入定义、`flow.save` → PUT 持久化；预览弹共享详情抽屉；读取 `wf_designer_pending` 自动选中 |
| `delegate.html` | 我的委托 | 双表（我委托的 / 委托给我的）+ 新建委托弹窗（受托人 + 限定流程）；启停 / 删除；生效委托改变待办可见性 |
| `monitor.html` | 流程监控 | 实例 / 任务状态统计卡 + 运行中实例表格（分页）；详情抽屉 |

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
| GET | `/api/todos?operator=` | 待办（operator 直属或 actor 表命中，含生效委托的委托人待办，行带 `delegated_from`） |
| GET | `/api/dones?operator=` | 已办（含受托视角委托人已办，行带 `delegated_from`） |
| POST | `/api/tasks/{id}/complete` | 办理：`{operator, action, comment, vars}`，action = `agree`（流转）/ `back`（退回发起节点）/ `reject`（终止）；受托人自动 act_as 以任务归属人执行 |
| POST | `/api/instances/{id}/cc` | 抄送实例：`{operators: [...]}`（同人对同人幂等） |
| GET | `/api/ccs?operator=` | 我的抄送列表 |
| GET | `/api/delegates` | 委托关系列表（前端按 operator 分「我委托的/委托给我的」） |
| POST | `/api/delegates` | 新建委托：`{operator, targetOperator, defineName?}`（defineName 空 = 全部流程） |
| POST | `/api/delegates/{id}/toggle` | 委托启用/停用 |
| DELETE | `/api/delegates/{id}` | 删除委托 |
| GET | `/api/monitor/overview` | 实例/任务状态统计 + 运行中实例 |
| GET | `/api/workspace/overview?operator=` | 工作台：个人卡 + 全局览（总数/进行中/已完成/今日/平均时长/驳回率/积压/逾期）+ 近 7 天趋势 + 流程 Top 5 + 状态分布 + 最近待办 |

## 设计器嵌入协议（iframe + postMessage）

设计器站点 `demo/public/assets/ui/index.html`（通过 `/assets/ui/index.html` 访问）可被任意 origin 的宿主 iframe 远程嵌入。URL 参数：

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

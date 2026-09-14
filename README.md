# Madong Workflow

> PHP 8.1+ 工作流引擎 | 包名：`madong/workflow` | 命名空间：`madong\workflow`
>
> 仓库：[Gitee · motion-code/workflow-engine](https://gitee.com/motion-code/workflow-engine) · [GitHub 镜像 · madong-code/workflow-engine](https://github.com/madong-code/workflow-engine)
>
> 当前为 **v3**（包名 `madong/workflow`）；v1（`madong/ingenious`）与 v2 为历史版本，早期仓库路径 [ingenstream/ingenious](https://gitee.com/ingenstream/ingenious)，已停止演进。

Madong Workflow 是一套基于 **PHP 8.1+ OOP** 设计的轻量、可扩展工作流引擎（演进自 Ingenious，命名空间与包名已独立）。它能够**解析流程设计器导出的 json 流程定义**并驱动流程流转，支持决策分支、并行合并、会签、自定义节点、事件、拦截器、模块化、子流程等能力。

## ✨ 特性

- **设计器驱动**：解析 LogicFlow 设计器导出的 json 流程定义，无需手写建模
- **节点丰富**：开始 / 结束 / 任务 / 决策 / 并行分支 / 合并 / 自定义 / 子流程
- **会签支持**：并行 / 串行会签，多实例任务与完成条件（`#nrOfCompletedInstances==N`）
- **条件路由**：`#变量` / `${变量}` 决策表达式，自动分支路由
- **拦截器体系**：节点拦截器 + AOP 流程拦截器（Aspect）
- **事件驱动**：EventBus 发布/订阅，流程生命周期事件
- **模块化**：Module 插拔扩展（依赖拓扑排序）
- **服务可替换**：通过 `I*Service` 接口对接任意持久化实现
- **开箱即用**：内存服务 + 真实流程 json 集成测试（86 tests / 184 assertions）

## 📦 安装

本包未发布到 Packagist，需在项目 `composer.json` 中配置 VCS 仓库后安装：

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitee.com/motion-code/workflow-engine.git"
        }
    ],
    "require": {
        "madong/workflow": "^3.0"
    }
}
```

GitHub 镜像源：`"url": "https://github.com/madong-code/workflow-engine.git"`

```bash
composer update madong/workflow
```

> 历史版本：`madong/ingenious`（v1，旧命名空间 `madong\ingenious\*`）与 v2 均在早期仓库 [ingenstream/ingenious](https://gitee.com/ingenstream/ingenious)，VCS url 指向该仓库可继续安装旧版；迁移到 v3 需将代码引用替换为 `madong\workflow\*`。

> 本地开发可将源码放在 `packages/workflow`，用 `{"type": "path", "url": "./packages/workflow"}` 仓库替代（path 优先级高于 vcs）。

**环境要求**

| 项目 | 要求 |
|------|------|
| PHP | ^8.1（readonly、enum、命名参数） |
| Composer | 2.x |
| 依赖 | `php-di/php-di` ^7.0、`madong/helper` ^1.0、`monolog/monolog` ^2.0\|^3.0 |

## 🚀 快速开始

```php
use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;
use madong\workflow\interface\services\{
    IProcessDefineService,
    IProcessInstanceService,
    IProcessTaskService,
};

// 1. 创建引擎
$engine = new Engine(new EngineConfig(
    services: [
        IProcessDefineService::class   => \App\Service\ProcessDefineService::class,
        IProcessInstanceService::class => \App\Service\ProcessInstanceService::class,
        IProcessTaskService::class     => \App\Service\ProcessTaskService::class,
    ],
    debug: true,
));

// 2. 发布流程定义
$defineService = $engine->getService(IProcessDefineService::class);
$defineService->createProcessDefine([
    'id'      => 'leave_001',
    'name'    => 'wf-leave',
    'content' => $jsonContent, // 流程定义 json 字符串
]);

// 3. 启动流程
$instance = $engine->startProcess('leave_001', 'user001', ['f_day' => 3]);

// 4. 完成任务
$taskService = $engine->getService(IProcessTaskService::class);
$tasks = $taskService->getDoingTaskList($instance->getId(), '');
$engine->completeTask($tasks[0]->getId(), 'user001', ['approved' => true]);
```

## 🧩 核心概念

| 概念 | 说明 |
|------|------|
| 流程定义 (ProcessDefine) | 设计器导出的 json「图纸」 |
| 流程模型 (ProcessModel) | 解析后的可执行对象模型 |
| 流程实例 (ProcessInstance) | 一次具体的流程执行 |
| 节点 (NodeModel) | 流程步骤（任务/判断/分支等） |
| 边 (TransitionModel) | 节点间流转，可带条件表达式 |
| 任务 (ProcessTask) | 推进到任务节点产生的待办 |

## 📐 流程定义规范

流程定义统一 **snake_case** 字段命名，8 种节点类型（`ingenious:start/end/task/decision/fork/join/custom/wfSubProcess`）。

类名引用（`clazz`/`assignment_handler`/拦截器）使用**点号形式**（json 不允许 `\`），引擎自动转为命名空间：

```json
{
  "name": "wf-leave",
  "display_name": "请假流程",
  "instance_url": "leaveForm",
  "nodes": [
    {
      "id": "approve",
      "type": "ingenious:task",
      "text": { "value": "部门审批" },
      "properties": {
        "assignee": "${manager}",
        "task_type": "Major",
        "perform_type": "ANY"
      }
    }
  ],
  "edges": []
}
```

## 🧰 扩展点

| 扩展点 | 机制 |
|--------|------|
| 自定义节点 | `clazz` 外部类，结果写回 `var`（类不存在时安全降级） |
| 节点拦截器 | `pre_interceptors` / `post_interceptors` |
| AOP 切面 | 实现 `Aspect`，按 `JoinPoint` 织入 |
| 事件监听 | 实现 `ProcessEventListener`，订阅 `EventBus` |
| 模块 | 实现 `Module`（register/boot/shutdown，依赖排序） |
| 新节点类型 | `NodeParser` + `NodeModel` + `Handler` |
| 第三方设计器 | 实现 `DesignerParserInterface` |

## 🧪 测试

```bash
vendor/bin/phpunit --no-coverage
```

```
PHPUnit 10.5.x
OK (86 tests, 184 assertions)
```

- 单元测试：`tests/unit/`
- 集成测试：`tests/integration/engine/`（含 5 个真实流程 json + 模块集成）
- 内存服务：`tests/fixture/memory/`
- 自定义类：`tests/fixture/handler/`、`tests/fixture/modular/`
- 流程定义归档：`tests/process/`

## 📚 文档

完整索引见 [docs/README.md](docs/README.md)。

| 文档 | 说明 |
|------|------|
| [docs/02-引擎API.md](docs/02-引擎API.md) | Engine 全部公开方法 |
| [docs/03-流程定义.md](docs/03-流程定义.md) | 流程定义 json 规范 |
| [docs/01-快速开始.md](docs/01-快速开始.md) | 最小可运行示例 |
| [docs/13-实现指南.md](docs/13-实现指南.md) | 服务实现与扩展 |
| [docs/14-框架基础.md](docs/14-框架基础.md) | 容器/AOP/事件/模块/表达式 |

> 宿主集成参考：MDAdmin 的 `workflow` 插件（`plugin/workflow`）已实现全部 15 个服务接口（MySQL 持久化 + 调度 + 消息推送），可作为生产级实现范例。

## 📄 License

[Apache-2.0](https://www.apache.org/licenses/LICENSE-2.0)

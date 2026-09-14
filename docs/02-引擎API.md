# 引擎 API

> 📚 阅读顺序 **2/14** · ◀ 上一节 [01-快速开始](01-快速开始.md) · ▶ 下一节 [03-流程定义](03-流程定义.md) · [目录](README.md)

> 工作流引擎唯一入口 `Engine`。本文档列出全部公开方法与组件访问器。

## 一、构造

```php
use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;

$engine = new Engine(new EngineConfig(
    services:    [...],  // 服务映射（接口 => 实现类）
    aspects:     [...],  // AOP 切面类列表
    modules:     [...],  // 自定义模块类列表
    extensions:  [...],  // 容器扩展参数
    debug:       false,  // 调试模式（开启切面追踪）
));
```

也可从文件/数组加载配置：

```php
$config = EngineConfig::fromFile('config/engine.php');
$config = EngineConfig::fromArray([...]);
```

## 二、流程方法

### startProcess — 启动流程

```php
public function startProcess(
    string  $definitionId,        // 流程定义 id
    string  $operator,            // 发起人
    array   $vars = [],           // 流程变量
    ?string $parentId = null,     // 父实例 id（子流程）
    ?string $parentNodeName = null,
): object                          // 返回 IProcessInstance
```

### completeTask — 完成任务

```php
public function completeTask(string $taskId, string $operator, array $vars = []): array
```

返回本次推进**新创建的任务列表**。

### jumpTask — 跳转节点

```php
public function jumpTask(string $taskId, string $operator, string $targetNodeName, array $vars = []): array
```

### rejectToFirst — 驳回首任务

```php
public function rejectToFirst(string $taskId, string $operator, array $vars = []): array
```

### rejectToEnd — 驳回结束

```php
public function rejectToEnd(string $taskId, string $operator): array
```

## 三、服务获取

```php
public function getService(string $interface): ?object
```

```php
$defineService = $engine->getService(IProcessDefineService::class);
$taskService   = $engine->getService(IProcessTaskService::class);
```

## 四、组件访问器

| 方法 | 返回 | 说明 |
|------|------|------|
| `getService(interface)` | ?object | 获取服务实现 |
| `getAspectDispatcher()` | AspectDispatcher | AOP 切面调度器 |
| `getEventBus()` | EventBus | 事件总线 |
| `getExpressionEngine()` | ExpressionEngine | 表达式引擎 |
| `getParserRegistry()` | NodeParserRegistry | 节点解析器注册表 |
| `getDesignerRegistry()` | DesignerParserRegistry | 设计器解析器注册表 |
| `getContainer()` | Container | DI 容器 |
| `getModuleManager()` | ModuleManager | 模块管理器 |

## 五、异常类型

| 异常 | 抛出场景 |
|------|----------|
| `DefinitionException` | 流程定义不存在 / 解析失败 / 缺少开始节点 |
| `StateException` | 实例或任务状态不允许当前操作（如已完成任务再办结） |
| `PermissionException` | 操作人无权处理该任务 |
| `NodeException` | 节点执行错误（解析/路由/类调用失败） |
| `ExecutionException` | 引擎执行期通用异常 |
| `FlowException` | 异常基类 |

> 业务侧建议捕获 `FlowException` 统一处理，再按子类型细分。

## 六、完整示例

```php
use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;
use madong\workflow\interface\services\IProcessDefineService;

$engine = new Engine(new EngineConfig(
    services: [
        IProcessDefineService::class => \App\Service\ProcessDefineService::class,
        // ... 其他服务
    ],
));

// 启动
$instance = $engine->startProcess('wf-leave', 'user001', ['f_day' => 3]);

// 查询待办
$taskService = $engine->getService(\madong\workflow\interface\services\IProcessTaskService::class);
$tasks = $taskService->getDoingTaskList($instance->getId(), '');

// 完成
$engine->completeTask($tasks[0]->getId(), 'user001', ['approved' => true]);
```

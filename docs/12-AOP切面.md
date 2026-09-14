# 08 · AOP 切面

> 📚 阅读顺序 **12/14** · ◀ 上一节 [11-任务调度](11-任务调度.md) · ▶ 下一节 [13-实现指南](13-实现指南.md) · [目录](README.md)

> 横切逻辑（审计/日志/风控/埋点）通过 `EngineConfig(aspects: [...])` 注册，按 `JoinPoint` + `AdviceType` 织入引擎方法调用。实现：`aop/AspectDispatcher`。

## 快速示例：任务创建审计

```php
use madong\workflow\aop\{Aspect, AspectContext, AdviceType, JoinPoint};
use madong\workflow\context\ExecutionContext;

class AuditAspect implements Aspect
{
    public function name(): string          { return 'audit'; }
    public function pointcut(): JoinPoint   { return JoinPoint::TASK_CREATE; }
    public function adviceType(): AdviceType { return AdviceType::AROUND; }
    public function priority(): int         { return 0; }

    public function invoke(AspectContext $ctx, ?callable $proceed = null): mixed
    {
        $before = microtime(true);
        $result = $proceed ? $proceed() : null;   // AROUND 必须手动放行

        audit_log('task_created', [
            'instance' => $ctx->getProcessInstanceId(),
            'node'     => $ctx->nodeName,
            'elapsed'  => microtime(true) - $before,
        ]);
        return $result;
    }
}
```

注册到引擎：

```php
$config = new EngineConfig(
    services: $serviceMap,
    aspects: [new AuditAspect()],      // 切面随引擎启动注册
    debug: true,                       // 开启后 AspectTrace 记录每个切面耗时/中断
);
$engine = EngineFactory::build($config);
```

## 织入时序（AspectDispatcher::dispatch）

```
目标方法调用
 1. BEFORE      顺序执行；任一切面 ctx.shouldProceed=false → 中断目标方法
 2. AROUND      按 priority 升序洋葱链包裹目标
 3. AFTER_THROW 目标抛异常时执行，异常原样抛出
 4. AFTER_RETURN 正常返回后执行
 5. AFTER       最后执行
```

| 规则 | 说明 |
|------|------|
| 排序 | 同 pointcut 内按 `priority()` 升序 |
| 唯一性 | 同 pointcut 内切面名重复抛 `FlowException` |
| 中断 | 仅 BEFORE 可中断（shouldProceed）；AROUND 不调 `$proceed()` 同样等于跳过目标 |
| 调试 | `debug: true` 时 AspectTrace 输出执行链与耗时 |

## 织入点全表（JoinPoint）

| JoinPoint | 时机 |
|-----------|------|
| `PROCESS_START` | 流程启动前 |
| `PROCESS_END` | 流程结束前 |
| `NODE_PRE_EXECUTE` | 节点执行前 |
| `NODE_POST_EXECUTE` | 节点执行后 |
| `NODE_AROUND_EXECUTE` | 节点执行环绕 |
| `TASK_CREATE` | 任务创建 |
| `TASK_COMPLETE` | 任务办结 |
| `TASK_ASSIGN` | 任务分配 |
| `TRANSITION_EXECUTE` | 边流转执行 |
| `ERROR_HANDLER` | 异常处理 |

## 切面上下文（AspectContext）

| 成员 | 说明 |
|------|------|
| `$joinPoint` | 当前织入点 |
| `$executionContext` | 执行上下文（`getVariable()` 取实例变量、`processInstanceId`） |
| `$node` / `$nodeName` | 当前节点模型 / 节点名（流程级织入点可为 null） |
| `$throwable` | AFTER_THROW 时可读到的异常 |
| `$returnValue` | 目标方法返回值（AFTER 系列可读） |
| `$shouldProceed` | 默认 true；BEFORE 中置 false 即中断 |

## BEFORE 拦截示例

```php
class RiskControlAspect implements Aspect
{
    public function pointcut(): JoinPoint { return JoinPoint::PROCESS_START; }
    public function adviceType(): AdviceType { return AdviceType::BEFORE; }

    public function invoke(AspectContext $ctx, ?callable $proceed = null): mixed
    {
        $blocked = risk_check($ctx->getVariable('f_amount'));
        if ($blocked) {
            $ctx->shouldProceed = false;   // 拦截启动
        }
        return null;
    }
}
```

## 多切面协作（priority 排序演示）

同一织入点注册多个切面时按 `priority()` 升序执行：

```php
$aspects = [
    new LogAspect(),       // priority: 0  → 第 1 个（最外层）
    new RiskControlAspect(), // priority: 5 → 第 2 个
    new MetricAspect(),    // priority: 10 → 第 3 个（最贴近目标）
];
```

AROUND 洋葱链：

```
LogAspect.invoke
  └─ RiskControlAspect.invoke
       └─ MetricAspect.invoke
            └─ 目标方法
```

外层切面可以决定是否放行里层（不调 `$proceed()` 直接 return 即短路整条链）。

## 实战：三切面完整配置

```php
$config = new EngineConfig(
    services: $serviceMap,
    aspects: [
        new \App\Workflow\Aspect\LogAspect(),        // BEFORE  TASK_CREATE   记录创建
        new \App\Workflow\Aspect\RiskControlAspect(), // BEFORE  PROCESS_START 风控拦截
        new \App\Workflow\Aspect\MetricAspect(),      // AROUND  NODE_AROUND_EXECUTE 节点耗时
    ],
    debug: true,
);
```

`debug: true` 时每次织入都会产生 Trace 记录（切面名、织入点、耗时、shouldProceed 状态），随引擎日志输出——上线前用它核对织入顺序，上线后关掉。

## 与事件总线的选择

| 需求 | 用切面 | 用事件 |
|------|--------|--------|
| 需要中断流程/方法 | ✅ | ❌（仅通知） |
| 需要包裹计时/改写返回 | ✅ AROUND | ❌ |
| 只做通知（发消息/写日志） | 可以 | ✅ 更轻（`EventType` 7 类事件，见[14-框架基础](14-框架基础.md)） |

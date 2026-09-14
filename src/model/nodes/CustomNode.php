<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\model\vo\Layout;
use madong\workflow\context\ExecutionContext;

class CustomNode extends NodeModel
{
    public function __construct(
        string  $name,
        string  $displayName = '',
        Layout  $layout = new Layout(0, 0, 0, 0),
        array   $inputs = [],
        array   $outputs = [],
        ?string $preInterceptors = null,
        ?string $postInterceptors = null,
        public readonly ?string $clazz = null,
        public readonly ?string $methodName = null,
        public readonly ?string $args = null,
        public readonly ?string $var = null,
    ) {
        parent::__construct($name, $displayName, $layout, $inputs, $outputs, $preInterceptors, $postInterceptors);
    }

    public function execute(ExecutionContext $ctx): void
    {
        // 若配置了自定义处理类且类可加载，则实例化调用并写入流程变量；否则安全降级为直接转发
        $clazz = $this->normalizeClazz($this->clazz);
        if ($clazz !== null && class_exists($clazz)) {
            try {
                $handler = new $clazz();
                $method = $this->methodName ?: 'handle';
                if (is_callable([$handler, $method])) {
                    $result = call_user_func([$handler, $method], $ctx, $this->resolveArgs($ctx));
                    if ($this->var !== null && $result !== null) {
                        $ctx->variables->set($this->var, $result);
                        // 持久化变量到实例
                        $instanceService = $ctx->engine->getService(
                            \madong\workflow\interface\services\IProcessInstanceService::class
                        );
                        if ($instanceService !== null && method_exists($instanceService, 'addVariable')) {
                            $instanceService->addVariable($ctx->processInstanceId, [$this->var => $result]);
                        }
                    }
                }
            } catch (\Throwable) {
                // 自定义处理器执行失败时降级，不中断流程
            }
        }

        foreach ($this->outputs as $transition) {
            if ($transition->evaluateEnabled($ctx)) {
                $transition->execute($ctx);
            }
        }
    }

    /**
     * 类名分隔符归一化：json 定义中不能直接使用 PHP 命名空间分隔符 "\"（转义/格式限制），
     * 故以点号 "." 分隔类名（如 madong.workflow.tests.fixture.handler.AmountCalculateHandler），
     * 此处将点号还原为 PHP 命名空间分隔符 "\"：
     *   madong.workflow.tests.fixture.handler.AmountCalculateHandler
     *   → madong\workflow\tests\fixture\handler\AmountCalculateHandler
     */
    private function normalizeClazz(?string $clazz): ?string
    {
        if ($clazz === null || $clazz === '') return null;
        return str_replace('.', '\\', $clazz);
    }

    /**
     * 解析 args：若为 ${var} / node.var / #var 形式则从流程变量取值，否则原样返回
     */
    private function resolveArgs(ExecutionContext $ctx): mixed
    {
        $args = $this->args;
        if ($args === null) return null;

        // ${var}
        if (preg_match('/^\$\{(\w+)\}$/', $args, $m)) {
            return $ctx->variables->get($m[1]);
        }
        // #var
        if (preg_match('/^#(\w+)$/', $args, $m)) {
            return $ctx->variables->get($m[1]);
        }
        // node.var
        if (str_contains($args, '.')) {
            $key = substr($args, strrpos($args, '.') + 1);
            return $ctx->variables->get($key);
        }
        return $args;
    }
}

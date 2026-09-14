<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * 节点拦截器测试类（对应文档 node-interceptor 示例）
 *
 * json pre_interceptors / post_interceptors 用点号：
 * madong.workflow.tests.fixture.handler.NodeAuditInterceptor
 * 在节点执行前后触发，用于审计、日志、通知等横切逻辑。
 */
class NodeAuditInterceptor
{
    /**
     * 节点执行前触发。
     */
    public function before(ExecutionContext $ctx): void
    {
        // 示例：记录操作审计
        error_log('[interceptor:before] operator=' . $ctx->operator . ' instance=' . $ctx->processInstanceId);
    }

    /**
     * 节点执行后触发。
     */
    public function after(ExecutionContext $ctx): void
    {
        // 示例：记录节点离开日志
        error_log('[interceptor:after] instance=' . $ctx->processInstanceId);
    }
}

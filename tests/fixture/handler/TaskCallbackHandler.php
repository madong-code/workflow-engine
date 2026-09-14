<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * 任务回调测试类
 *
 * json callback 用点号：
 * madong.workflow.tests.fixture.handler.TaskCallbackHandler
 * 在任务完成/节点执行后触发，用于业务通知、数据同步等。
 */
class TaskCallbackHandler
{
    /**
     * 任务完成回调。
     */
    public function onComplete(ExecutionContext $ctx): void
    {
        // 示例：任务完成后业务处理
        error_log('[callback:complete] instance=' . $ctx->processInstanceId);
    }
}

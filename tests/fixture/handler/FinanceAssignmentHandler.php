<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * 财务处理人分配器测试类
 *
 * json assignment_handler 用点号：
 * madong.workflow.tests.fixture.handler.FinanceAssignmentHandler
 */
class FinanceAssignmentHandler
{
    /**
     * 返回财务处理人。
     */
    public function assign(ExecutionContext $ctx, mixed $args = null): array|string
    {
        return 'finance_manager';
    }
}

<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * HR 处理人分配器测试类
 *
 * json assignment_handler 用点号：
 * madong.workflow.tests.fixture.handler.HrAssignmentHandler
 */
class HrAssignmentHandler
{
    /**
     * 返回 HR 处理人。
     */
    public function assign(ExecutionContext $ctx, mixed $args = null): array|string
    {
        return 'hr_manager';
    }
}

<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * 老板处理人分配器测试类
 *
 * json assignment_handler 用点号：
 * madong.workflow.tests.fixture.handler.BossAssignmentHandler
 */
class BossAssignmentHandler
{
    /**
     * 返回老板处理人。
     */
    public function assign(ExecutionContext $ctx, mixed $args = null): array|string
    {
        return 'boss_manager';
    }
}

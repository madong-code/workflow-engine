<?php

namespace madong\workflow\tests\fixture\handler;

use madong\workflow\context\ExecutionContext;

/**
 * 部门处理人分配器测试类
 *
 * json assignment_handler 用点号：
 * madong.workflow.tests.fixture.handler.DeptAssignmentHandler
 * 根据部门返回处理人列表。
 */
class DeptAssignmentHandler
{
    /**
     * 根据流程变量分配处理人。
     *
     * @param ExecutionContext $ctx
     * @param mixed $args 可选参数（如部门键）
     * @return array|string 处理人列表或单个处理人
     */
    public function assign(ExecutionContext $ctx, mixed $args = null): array|string
    {
        $dept = $ctx->getVariable('dept', '');
        $managers = [
            'finance' => 'finance_manager',
            'hr'      => 'hr_manager',
            'sales'   => 'sales_manager',
        ];
        return $managers[$dept] ?? 'default_manager';
    }
}

<?php

namespace madong\workflow\handler;

use madong\workflow\context\ExecutionContext;
use madong\workflow\enum\{ProcessState, SubmitType, ProcessConst, EventType};
use madong\workflow\aop\{JoinPoint, AspectContext};

class EndProcessHandler implements Handler
{
    public function handle(ExecutionContext $ctx): void
    {
        $instanceService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessInstanceService::class
        );

        // 1. 判断提交类型，更新实例状态
        $submitType = $ctx->getVariable(ProcessConst::SUBMIT_TYPE, SubmitType::AGREE->value);
        if ($submitType === SubmitType::REJECT->value) {
            $instanceService->rejectProcessInstance($ctx->processInstanceId);
        } else {
            $instanceService->finishProcessInstance($ctx->processInstanceId);
        }

        // 1.1 兜底清理：并行分支/多出边场景下，实例结束时可能还有未办任务（DOING/SUSPENDED），
        //     不清理会变成幽灵待办（实例已结束但任务永远挂在办理人待办列表）
        $taskService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessTaskService::class
        );
        if ($taskService !== null && method_exists($taskService, 'cancelAllDoingTasks')) {
            $taskService->cancelAllDoingTasks($ctx->processInstanceId, $ctx->operator);
        }

        // 2. AOP: PROCESS_END 切面
        $dispatcher = $ctx->engine->getAspectDispatcher();
        $aspectCtx = new AspectContext(JoinPoint::PROCESS_END, $ctx);
        $dispatcher->dispatch(JoinPoint::PROCESS_END, $aspectCtx, fn() => null);

        // 3. 事件: 流程结束
        $ctx->engine->getEventBus()->publish(EventType::PROCESS_END->value, $ctx->processInstanceId);

        // 4. 子流程回调: 恢复父流程
        $processInstance = $ctx->processInstance;
        if ($processInstance !== null && $processInstance->getParentId() !== null) {
            $this->resumeParentProcess($ctx, $processInstance->getParentId(), $processInstance->getParentNodeName());
        }
    }

    private function resumeParentProcess(ExecutionContext $ctx, string $parentId, string $parentNodeName): void
    {
        $instanceService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessInstanceService::class
        );
        $defineService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessDefineService::class
        );

        $parentInstance = $instanceService->getById($parentId);
        if ($parentInstance === null) return;

        $processModel = $defineService->getProcessModel($parentInstance->getProcessDefineId());
        if ($processModel === null) return;

        $subProcessNode = $processModel->getNode($parentNodeName);
        if ($subProcessNode === null) return;

        $parentCtx = new ExecutionContext(
            processInstanceId: $parentId,
            processTaskId: 0,
            operator: $ctx->operator,
            engine: $ctx->engine,
            processInstance: $parentInstance,
            processModel: $processModel,
            variables: $ctx->variables,
        );

        $subProcessNode->execute($parentCtx);
        $ctx->addTasks($parentCtx->getCreatedTasks());
    }
}

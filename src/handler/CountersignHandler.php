<?php

namespace madong\workflow\handler;

use madong\workflow\context\ExecutionContext;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\enum\{CountersignType, ProcessConst};

class CountersignHandler implements Handler
{
    public function __construct(
        private readonly TaskNode $taskNode,
    ) {}

    public function handle(ExecutionContext $ctx): void
    {
        // 首次进入会签节点：当前任务不是本会签节点（processTask 为 null 或 taskName 不匹配）→ 创建多实例会签任务
        $isCurrentTaskNode = $ctx->processTask !== null
            && $ctx->processTask->getTaskName() === $this->taskNode->name;
        if (!$isCurrentTaskNode) {
            $taskService = $ctx->engine->getService(
                \madong\workflow\interface\services\IProcessTaskService::class
            );
            $tasks = $taskService->createCountersignTask($this->taskNode, $ctx);
            $ctx->addTasks($tasks);
            $ctx->result->setMerged(false);
            return;
        }

        $prefix = ProcessConst::COUNTERSIGN_PREFIX . $this->taskNode->name . "_";
        $operatorList = $ctx->getVariable($prefix . ProcessConst::OPERATOR_LIST, []);
        $loopCounter = array_search($ctx->operator, $operatorList);

        if ($loopCounter !== false) {
            $ctx->setVariable($prefix . ProcessConst::LOOP_COUNTER, $loopCounter);
        }

        $completed = $ctx->getVariable($prefix . ProcessConst::NR_OF_COMPLETED, 0) + 1;
        $ctx->setVariable($prefix . ProcessConst::NR_OF_COMPLETED, $completed);

        // 持久化会签变量，保证跨多次 completeTask 调用保持
        $instanceService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessInstanceService::class
        );
        if ($instanceService !== null && method_exists($instanceService, 'addVariable')) {
            $instanceService->addVariable($ctx->processInstanceId, [
                $prefix . ProcessConst::OPERATOR_LIST => $operatorList,
                $prefix . ProcessConst::NR_OF_COMPLETED => $completed,
            ]);
        }

        $isMerged = $this->evaluateCondition($ctx, $prefix, $operatorList, $loopCounter);
        $ctx->result->setMerged($isMerged);

        if ($isMerged) {
            $this->cleanupCountersignVars($ctx, $prefix);
        }
    }

    private function evaluateCondition(ExecutionContext $ctx, string $prefix, array $operators, int|false $loopCounter): bool
    {
        $condition = $this->taskNode->countersignCondition;
        $type = $this->taskNode->countersignType ?? CountersignType::PARALLEL;

        if ($condition === 'ONE_VOTE_VETO') {
            return $ctx->getVariable(ProcessConst::COUNTERSIGN_DISAGREE_FLAG, false) === true;
        }

        if (!empty($condition)) {
            // 注入标准会签计数变量，供 #nrOfCompletedInstances / #nrOfActiveInstances 等条件求值
            $completed = $ctx->getVariable($prefix . ProcessConst::NR_OF_COMPLETED, 0);
            $ctx->setVariable('nrOfCompletedInstances', $completed);
            $ctx->setVariable('nrOfActiveInstances', count($operators) - $completed);
            $ctx->setVariable('nrOfInstances', count($operators));
            return $ctx->engine->getExpressionEngine()->evaluateToBool($condition, $ctx->variables);
        }

        if ($type === CountersignType::SEQUENTIAL) {
            return $loopCounter !== false && $loopCounter === array_key_last($operators);
        }

        $taskService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessTaskService::class
        );
        return empty($taskService->getDoingTaskList($ctx->processInstanceId, ''));
    }

    private function cleanupCountersignVars(ExecutionContext $ctx, string $prefix): void
    {
        // 清理会签变量
    }
}

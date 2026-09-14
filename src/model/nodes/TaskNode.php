<?php

namespace madong\workflow\model\nodes;

use madong\workflow\model\NodeModel;
use madong\workflow\model\vo\Layout;
use madong\workflow\enum\{PerformType, CountersignType, TaskType};
use madong\workflow\context\ExecutionContext;

class TaskNode extends NodeModel
{
    public function __construct(
        string  $name,
        string  $displayName = '',
        Layout  $layout = new Layout(0, 0, 0, 0),
        array   $inputs = [],
        array   $outputs = [],
        ?string $preInterceptors = null,
        ?string $postInterceptors = null,
        public readonly ?string $form = null,
        public readonly ?string $assignee = null,
        public readonly ?string $assigneeFormKey = null,
        public readonly ?string $groupKey = null,
        public readonly ?string $assignmentHandler = null,
        public readonly ?TaskType $taskType = null,
        public readonly ?PerformType $performType = null,
        public readonly ?CountersignType $countersignType = null,
        public readonly ?string $countersignCondition = null,
        public readonly ?string $candidateUsers = null,
        public readonly ?string $candidateGroups = null,
        public readonly ?string $candidateHandler = null,
        public readonly ?string $reminderTime = null,
        public readonly ?string $reminderRepeat = null,
        public readonly ?string $expireTime = null,
        public readonly ?string $autoExecute = null,
        public readonly ?string $callback = null,
        public readonly mixed  $ext = null,
    ) {
        parent::__construct($name, $displayName, $layout, $inputs, $outputs, $preInterceptors, $postInterceptors);
    }

    public function execute(ExecutionContext $ctx): void
    {
        $isCurrentTaskNode = $ctx->processTask !== null && $this->name === $ctx->processTask->getTaskName();
        $isCountersign = $this->performType === PerformType::COUNTERSIGN;

        if ($isCurrentTaskNode) {
            // 运行时协办任务（待办里发起的加签/会签成员）办结：
            // 协办组推进（串行下一顺位/恢复锚点）已由 ProcessTaskService::onCosignTaskFinished 处理，
            // 此处直接结束，避免协办任务误入定义级会签计数（污染 nrOfCompletedInstances）或触发节点流转
            if (!empty($ctx->processTask->getData('is_cosign'))) {
                $ctx->result->setMerged(false);
                return;
            }

            // 正在完成任务
            if ($isCountersign) {
                // 会签任务完成：先做合并判断，全部完成才继续执行后续边
                $handler = new \madong\workflow\handler\CountersignHandler($this);
                $handler->handle($ctx);
                if ($ctx->result->isMerged()) {
                    foreach ($this->outputs as $transition) {
                        if ($transition->evaluateEnabled($ctx)) {
                            $transition->execute($ctx);
                        }
                    }
                }
                return;
            }

            // 运行时加签/发起会签（独立协办表 wf_process_cosign）：
            //   WAIT   → 协办组未办结，等待（与 CountersignHandler 合并语义一致）
            //   RESUME → 前加签/会签组办结，锚点任务待恢复由发起人终审，此处不流转
            //   FLOW   → 后加签组办结（或锚点终审），继续执行后续边
            $taskService = $ctx->engine->getService(
                \madong\workflow\interface\services\IProcessTaskService::class
            );
            if ($taskService !== null && method_exists($taskService, 'getCosignGroupState')) {
                $cosignState = $taskService->getCosignGroupState($ctx->processTask);
                if ($cosignState === \madong\workflow\interface\services\IProcessTaskService::COSIGN_WAIT) {
                    $ctx->result->setMerged(false);
                    return;
                }
                if ($cosignState === \madong\workflow\interface\services\IProcessTaskService::COSIGN_RESUME) {
                    return;
                }
                if ($cosignState === \madong\workflow\interface\services\IProcessTaskService::COSIGN_FLOW) {
                    foreach ($this->outputs as $transition) {
                        if ($transition->evaluateEnabled($ctx)) {
                            $transition->execute($ctx);
                        }
                    }
                    return;
                }
            }

            // 存量兜底：无协办组记录的旧版运行时加签（同节点 DOING 兄弟任务）等待全部办结
            if ($taskService !== null) {
                $siblings = $taskService->getDoingTaskList($ctx->processInstanceId, $this->name);
                foreach ($siblings as $sibling) {
                    if ($sibling instanceof \madong\workflow\interface\model\IProcessTask
                        && $sibling->getId() !== $ctx->processTask->getId()) {
                        $ctx->result->setMerged(false);
                        return;
                    }
                }
            }

            // 普通任务节点，只执行后续边
            foreach ($this->outputs as $transition) {
                if ($transition->evaluateEnabled($ctx)) {
                    $transition->execute($ctx);
                }
            }
            return;
        }

        // 新任务节点，创建任务，不继续执行
        if ($isCountersign) {
            $handler = new \madong\workflow\handler\CountersignHandler($this);
        } else {
            $handler = new \madong\workflow\handler\CreateTaskHandler($this);
        }
        $handler->handle($ctx);
    }
}

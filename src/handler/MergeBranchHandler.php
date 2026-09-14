<?php

namespace madong\workflow\handler;

use madong\workflow\context\ExecutionContext;
use madong\workflow\model\NodeModel;
use madong\workflow\model\nodes\{ForkNode, TaskNode, JoinNode};

class MergeBranchHandler implements Handler
{
    public function __construct(
        private readonly JoinNode $joinNode,
    ) {}

    public function handle(ExecutionContext $ctx): void
    {
        $taskService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessTaskService::class
        );

        $activeNodes = $this->findActiveNodes();
        $doingTasks = $taskService->getDoingTaskList($ctx->processInstanceId, implode(',', $activeNodes));
        $ctx->result->setMerged(empty($doingTasks));
    }

    private function findActiveNodes(): array
    {
        $names = [];
        $this->collectTaskNames($this->joinNode, $names);
        return $names;
    }

    private function collectTaskNames(NodeModel $node, array &$names): void
    {
        if ($node instanceof ForkNode) return;
        foreach ($node->inputs as $transition) {
            $source = $transition->source;
            if ($source === null) continue;
            if ($source instanceof TaskNode) {
                $names[] = $source->name;
            }
            $this->collectTaskNames($source, $names);
        }
    }
}

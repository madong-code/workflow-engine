<?php

namespace madong\workflow\model\builder;

use madong\workflow\enum\{PerformType, CountersignType, TaskType};
use madong\workflow\model\nodes\TaskNode;

class TaskNodeBuilder extends NodeBuilder
{
    private ?string $assignee = null;
    private ?string $form = null;
    private ?string $assigneeFormKey = null;
    private ?string $groupKey = null;
    private ?string $assignmentHandler = null;
    private ?TaskType $taskType = null;
    private ?PerformType $performType = null;
    private ?CountersignType $countersignType = null;
    private ?string $countersignCondition = null;
    private ?string $candidateUsers = null;
    private ?string $candidateGroups = null;
    private ?string $candidateHandler = null;
    private ?string $reminderTime = null;
    private ?string $reminderRepeat = null;
    private ?string $expireTime = null;
    private ?string $autoExecute = null;
    private ?string $callback = null;
    private mixed $ext = null;

    public function assignee(?string $v): self { $this->assignee = $v; return $this; }
    public function form(?string $v): self { $this->form = $v; return $this; }
    public function assigneeFormKey(?string $v): self { $this->assigneeFormKey = $v; return $this; }
    public function groupKey(?string $v): self { $this->groupKey = $v; return $this; }
    public function assignmentHandler(?string $v): self { $this->assignmentHandler = $v; return $this; }
    public function taskType(?TaskType $v): self { $this->taskType = $v; return $this; }
    public function performType(?PerformType $v): self { $this->performType = $v; return $this; }
    public function countersignType(?CountersignType $v): self { $this->countersignType = $v; return $this; }
    public function countersignCondition(?string $v): self { $this->countersignCondition = $v; return $this; }
    public function candidateUsers(?string $v): self { $this->candidateUsers = $v; return $this; }
    public function candidateGroups(?string $v): self { $this->candidateGroups = $v; return $this; }
    public function candidateHandler(?string $v): self { $this->candidateHandler = $v; return $this; }
    public function reminderTime(?string $v): self { $this->reminderTime = $v; return $this; }
    public function reminderRepeat(?string $v): self { $this->reminderRepeat = $v; return $this; }
    public function expireTime(?string $v): self { $this->expireTime = $v; return $this; }
    public function autoExecute(?string $v): self { $this->autoExecute = $v; return $this; }
    public function callback(?string $v): self { $this->callback = $v; return $this; }
    public function ext(mixed $v): self { $this->ext = $v; return $this; }

    public function build(): TaskNode
    {
        return new TaskNode(
            ...$this->buildBase(),
            form: $this->form,
            assignee: $this->assignee,
            assigneeFormKey: $this->assigneeFormKey,
            groupKey: $this->groupKey,
            assignmentHandler: $this->assignmentHandler,
            taskType: $this->taskType,
            performType: $this->performType,
            countersignType: $this->countersignType,
            countersignCondition: $this->countersignCondition,
            candidateUsers: $this->candidateUsers,
            candidateGroups: $this->candidateGroups,
            candidateHandler: $this->candidateHandler,
            reminderTime: $this->reminderTime,
            reminderRepeat: $this->reminderRepeat,
            expireTime: $this->expireTime,
            autoExecute: $this->autoExecute,
            callback: $this->callback,
            ext: $this->ext,
        );
    }
}

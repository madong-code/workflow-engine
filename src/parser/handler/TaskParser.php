<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\NodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\TaskNode;
use madong\workflow\model\builder\TaskNodeBuilder;
use madong\workflow\enum\{TaskType, PerformType, CountersignType};

class TaskParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        $taskBuilder = new TaskNodeBuilder();
        $taskBuilder->copyFrom($this->builder);

        $props = $lfNode->getProperties();
        if ($props !== null) {
            // field 子对象（LogicFlow 导出会签配置字段：countersign_type、countersign_completion_condition）
            $field = $this->getProp($props, 'field');

            $taskBuilder
                ->form($this->getProp($props, NodeParser::FORM_KEY))
                ->assignee($this->getProp($props, NodeParser::ASSIGNEE_KEY))
                ->assigneeFormKey($this->getProp($props, NodeParser::ASSIGNEE_FORM_KEY))
                ->groupKey($this->getProp($props, NodeParser::GROUP_KEY))
                ->assignmentHandler($this->getProp($props, NodeParser::ASSIGNMENT_HANDLE_KEY))
                ->taskType(TaskType::fromCode($this->getProp($props, NodeParser::TASK_TYPE_KEY)))
                ->performType(PerformType::fromCode($this->getProp($props, NodeParser::PERFORM_TYPE_KEY)))
                ->reminderTime($this->getProp($props, NodeParser::REMINDER_TIME_KEY))
                ->reminderRepeat($this->getProp($props, NodeParser::REMINDER_REPEAT_KEY))
                ->expireTime($this->getProp($props, NodeParser::EXPIRE_TIME_KEY))
                ->autoExecute($this->getProp($props, NodeParser::AUTH_EXECUTE_KEY))
                ->callback($this->getProp($props, NodeParser::CALLBACK_KEY))
                ->candidateUsers($this->getProp($props, NodeParser::EXT_FIELD_CANDIDATE_USERS))
                ->candidateGroups($this->getProp($props, NodeParser::EXT_FIELD_CANDIDATE_GROUPS))
                ->candidateHandler($this->getProp($props, NodeParser::EXT_FIELD_CANDIDATE_HANDLER))
                ->countersignType(CountersignType::fromCode($this->getProp($field ?? $props, NodeParser::EXT_FIELD_COUNTERSIGN_TYPE)))
                ->countersignCondition($this->getProp($field ?? $props, NodeParser::EXT_FIELD_COUNTERSIGN_CONDITION));

            $ext = $this->getProp($props, NodeParser::EXT_FIELD_KEY);
            if ($ext !== null) {
                $taskBuilder->ext($ext);
            }
        }

        $this->builder = $taskBuilder;
    }

    private function getProp(mixed $props, string $key): mixed
    {
        if ($props === null) return null;

        // 统一 snake_case 键读取
        if (is_array($props) || $props instanceof \ArrayAccess) {
            if (isset($props[$key])) return $props[$key];
        } elseif (is_object($props) && property_exists($props, $key)) {
            return $props->$key;
        }
        return null;
    }

    public function build(): TaskNode
    {
        if ($this->builder instanceof TaskNodeBuilder) {
            return $this->builder->build();
        }
        throw new \LogicException('TaskParser builder 未正确初始化');
    }
}

<?php

namespace madong\workflow\parser;

use madong\workflow\model\NodeModel;

interface NodeParser
{
    const NODE_TYPE_PREFIX = 'ingenious:';

    // TaskNode 属性键名（统一 snake_case）
    const FORM_KEY                    = 'form';
    const ASSIGNEE_KEY                = 'assignee';
    const ASSIGNEE_FORM_KEY           = 'assignee_form_key';
    const GROUP_KEY                   = 'group_key';
    const ASSIGNMENT_HANDLE_KEY       = 'assignment_handler';
    const TASK_TYPE_KEY               = 'task_type';
    const PERFORM_TYPE_KEY            = 'perform_type';
    const REMINDER_TIME_KEY           = 'reminder_time';
    const REMINDER_REPEAT_KEY         = 'reminder_repeat';
    const EXPIRE_TIME_KEY             = 'expire_time';
    const AUTH_EXECUTE_KEY            = 'auto_execute';
    const CALLBACK_KEY                = 'callback';
    const EXT_FIELD_KEY               = 'ext';
    const EXT_FIELD_CANDIDATE_USERS   = 'candidate_users';
    const EXT_FIELD_CANDIDATE_GROUPS  = 'candidate_groups';
    const EXT_FIELD_CANDIDATE_HANDLER = 'candidate_handler';
    const EXT_FIELD_COUNTERSIGN_TYPE  = 'countersign_type';
    const EXT_FIELD_COUNTERSIGN_CONDITION = 'countersign_completion_condition';

    public function parse(\madong\workflow\parser\lf\LfNode $lfNode, array $edges): void;
    public function build(): NodeModel;
}

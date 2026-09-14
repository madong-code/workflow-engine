<?php

namespace madong\workflow\aop;

enum JoinPoint: string
{
    case PROCESS_START       = 'process.start';
    case PROCESS_END         = 'process.end';
    case NODE_PRE_EXECUTE    = 'node.preExecute';
    case NODE_POST_EXECUTE   = 'node.postExecute';
    case NODE_AROUND_EXECUTE = 'node.aroundExecute';
    case TASK_CREATE         = 'task.create';
    case TASK_COMPLETE       = 'task.complete';
    case TASK_ASSIGN         = 'task.assign';
    case TRANSITION_EXECUTE  = 'transition.execute';
    case ERROR_HANDLER       = 'error.handler';
}

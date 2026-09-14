<?php

namespace madong\workflow\enum;

enum EventType: string
{
    case PROCESS_START  = 'process.start';
    case PROCESS_END    = 'process.end';
    case TASK_START     = 'task.start';
    case TASK_COMPLETE  = 'task.complete';
    case TASK_ASSIGN    = 'task.assign';
    case NODE_ENTER     = 'node.enter';
    case NODE_LEAVE     = 'node.leave';

    public function label(): string
    {
        return match ($this) {
            self::PROCESS_START  => '流程启动',
            self::PROCESS_END    => '流程结束',
            self::TASK_START     => '任务启动',
            self::TASK_COMPLETE  => '任务完成',
            self::TASK_ASSIGN    => '任务分配',
            self::NODE_ENTER     => '节点进入',
            self::NODE_LEAVE     => '节点离开',
        };
    }
}

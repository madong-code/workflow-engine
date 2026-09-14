<?php

return [
    // 服务映射 (接口 => 实现类)
    'services' => [
        // \madong\workflow\interface\services\IProcessDefineService::class => \App\Service\ProcessDefineService::class,
        // \madong\workflow\interface\services\IProcessInstanceService::class => \App\Service\ProcessInstanceService::class,
        // \madong\workflow\interface\services\IProcessTaskService::class => \App\Service\ProcessTaskService::class,
    ],

    // 切面配置
    'aspects' => [
        // \App\Workflow\AuditAspect::class,
    ],

    // 模块配置
    'modules' => [
        // \App\Workflow\NotifyModule::class,
    ],

    // 扩展参数
    'extensions' => [
        // 'timeout' => 3600,
    ],

    // 调试模式
    'debug' => false,
];

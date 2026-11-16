<?php
/**
 * workflow demo · 引擎装配工厂（PDO 存储版，仿插件 EngineFactory）
 *
 * 注入 7 个核心服务的 PDO 实现（app/model/store），可选服务由引擎按需判空。
 */

declare(strict_types=1);

namespace madong\workflow\demo\engine;

use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;
use madong\workflow\demo\model\Database;
use madong\workflow\demo\model\store\{
    PdoProcessDefineService,
    PdoProcessInstanceService,
    PdoProcessTaskService,
    PdoProcessTaskActorService,
    PdoProcessInstanceHistoryService,
    PdoProcessTaskHistoryService,
    PdoProcessTaskActorHistoryService
};
use madong\workflow\interface\services\{
    IProcessDefineService,
    IProcessInstanceService,
    IProcessTaskService,
    IProcessTaskActorService,
    IProcessInstanceHistoryService,
    IProcessTaskHistoryService,
    IProcessTaskActorHistoryService
};

final class EngineFactory
{
    private static ?Engine $engine = null;

    public static function create(): Engine
    {
        if (self::$engine instanceof Engine) {
            return self::$engine;
        }

        $pdo = Database::pdo();

        $actorService       = new PdoProcessTaskActorService($pdo);
        $taskHistoryService = new PdoProcessTaskHistoryService($pdo);

        self::$engine = new Engine(new EngineConfig(
            services: [
                IProcessDefineService::class            => new PdoProcessDefineService($pdo),
                IProcessInstanceService::class          => new PdoProcessInstanceService($pdo),
                IProcessTaskService::class              => new PdoProcessTaskService($pdo, $actorService, $taskHistoryService),
                IProcessTaskActorService::class         => $actorService,
                IProcessInstanceHistoryService::class   => new PdoProcessInstanceHistoryService($pdo),
                IProcessTaskHistoryService::class       => $taskHistoryService,
                IProcessTaskActorHistoryService::class  => new PdoProcessTaskActorHistoryService($pdo),
            ],
            debug: false,
        ));

        return self::$engine;
    }
}

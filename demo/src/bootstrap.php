<?php
/**
 * workflow demo · 极简引导文件
 *
 * - 提供 madong\workflow 的 PSR-4 自动加载（不依赖 composer install / vendor）
 * - 提供 demo 自身（madong\workflow\demo）的自动加载
 * - 提供装配好的引擎工厂（PDO 存储版，仿插件 EngineFactory）
 *
 * 该文件被 public/index.php 与 src/api.php 以 require_once 引入，幂等。
 */

declare(strict_types=1);

namespace madong\workflow\demo;

use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;
use madong\workflow\interface\services\{
    IProcessDefineService,
    IProcessInstanceService,
    IProcessTaskService,
    IProcessTaskActorService,
    IProcessInstanceHistoryService,
    IProcessTaskHistoryService,
    IProcessTaskActorHistoryService
};
use madong\workflow\demo\store\{
    PdoProcessDefineService,
    PdoProcessInstanceService,
    PdoProcessTaskService,
    PdoProcessTaskActorService,
    PdoProcessInstanceHistoryService,
    PdoProcessTaskHistoryService,
    PdoProcessTaskActorHistoryService
};

if (!function_exists('madong\\workflow\\demo\\engine')) {
    // 引擎 PSR-4 兜底加载（demo 独立运行时无 composer；若 vendor 已加载则此处静默跳过）
    spl_autoload_register(static function (string $class): void {
        $enginePrefix = 'madong\\workflow\\';
        if (str_starts_with($class, $enginePrefix)) {
            $rel  = substr($class, strlen($enginePrefix));
            $file = __DIR__ . '/../../src/' . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }

        $demoPrefix = 'madong\\workflow\\demo\\';
        if (str_starts_with($class, $demoPrefix)) {
            $rel  = substr($class, strlen($demoPrefix));
            $file = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }, true, true);

    /**
     * 引擎单例（每次进程复用）：注入 7 个核心服务的 PDO 实现
     */
    function engine(): Engine
    {
        static $engine = null;
        if ($engine instanceof Engine) {
            return $engine;
        }

        $pdo = Database::pdo();

        $actorService      = new PdoProcessTaskActorService($pdo);
        $taskHistoryService = new PdoProcessTaskHistoryService($pdo);

        $engine = new Engine(new EngineConfig(
            services: [
                IProcessDefineService::class             => new PdoProcessDefineService($pdo),
                IProcessInstanceService::class           => new PdoProcessInstanceService($pdo),
                IProcessTaskService::class               => new PdoProcessTaskService($pdo, $actorService, $taskHistoryService),
                IProcessTaskActorService::class          => $actorService,
                IProcessInstanceHistoryService::class    => new PdoProcessInstanceHistoryService($pdo),
                IProcessTaskHistoryService::class        => $taskHistoryService,
                IProcessTaskActorHistoryService::class   => new PdoProcessTaskActorHistoryService($pdo),
            ],
            debug: false,
        ));

        return $engine;
    }

    /**
     * 确保库表与种子就绪（幂等，首个请求触发）
     */
    function ensureDatabase(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        Database::ensureSchema();
        $done = true;
    }
}

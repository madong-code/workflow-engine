<?php

namespace madong\workflow\tests\fixture;

use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;
use madong\workflow\interface\services\{
    IProcessDefineService,
    IProcessInstanceService,
    IProcessTaskService,
    IProcessTaskActorService,
    IProcessInstanceHistoryService,
    IProcessTaskHistoryService,
};
use madong\workflow\tests\fixture\memory\{
    MemoryProcessDefineService,
    MemoryProcessInstanceService,
    MemoryProcessTaskService,
    MemoryProcessTaskActorService,
    MemoryProcessInstanceHistoryService,
    MemoryProcessTaskHistoryService,
};

class TestEngineFactory
{
    public static function create(): Engine
    {
        return new Engine(new EngineConfig(
            services: [
                IProcessDefineService::class           => MemoryProcessDefineService::class,
                IProcessInstanceService::class         => MemoryProcessInstanceService::class,
                IProcessTaskService::class             => MemoryProcessTaskService::class,
                IProcessTaskActorService::class        => MemoryProcessTaskActorService::class,
                IProcessInstanceHistoryService::class  => MemoryProcessInstanceHistoryService::class,
                IProcessTaskHistoryService::class      => MemoryProcessTaskHistoryService::class,
            ],
            debug: true,
        ));
    }

    public static function createWithDefinition(string $jsonFile): Engine
    {
        $engine = self::create();
        // 修正路径问题
        $absFile = realpath($jsonFile);
        if ($absFile === false) {
            throw new \RuntimeException("无法找到文件: $jsonFile");
        }
        $content = file_get_contents($absFile);
        if ($content === false) {
            throw new \RuntimeException("无法读取文件: $absFile");
        }
        $defineService = $engine->getService(IProcessDefineService::class);
        $defineService->createProcessDefine([
            'id'      => '1',
            'name'    => 'test_process',
            'content' => $content,
        ]);
        return $engine;
    }
}

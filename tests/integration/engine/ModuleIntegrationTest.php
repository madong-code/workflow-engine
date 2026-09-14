<?php

namespace madong\workflow\tests\integration\engine;

use PHPUnit\Framework\TestCase;
use madong\workflow\Engine;
use madong\workflow\config\EngineConfig;
use madong\workflow\modular\ModuleManager;
use madong\workflow\tests\fixture\modular\CustomNotifyModule;
use madong\workflow\tests\fixture\modular\ServiceInjectModule;

/**
 * 引擎模块集成测试：验证 Engine 构造时模块的安装与启动流程。
 */
class ModuleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 重置静态调用标志，避免测试间污染
        CustomNotifyModule::$registered = false;
        CustomNotifyModule::$booted = false;
        CustomNotifyModule::$shutdown = false;
    }

    public function testEngineInstallsBuiltinModules(): void
    {
        $engine = new Engine(new EngineConfig());
        $manager = $engine->getModuleManager();

        $this->assertTrue($manager->isInstalled('core'));
        $this->assertTrue($manager->isInstalled('parser'));
        $this->assertTrue($manager->isInstalled('event'));
        $this->assertTrue($manager->isInstalled('interceptor'));
    }

    public function testEngineInstallsCustomModuleFromConfig(): void
    {
        $engine = new Engine(new EngineConfig(
            modules: [CustomNotifyModule::class],
        ));

        $this->assertTrue($engine->getModuleManager()->isInstalled('custom_notify'));
    }

    public function testCustomModuleRegisterAndBootCalled(): void
    {
        new Engine(new EngineConfig(
            modules: [CustomNotifyModule::class],
        ));

        // Engine 构造时 install + bootAll
        $this->assertTrue(CustomNotifyModule::$registered);
        $this->assertTrue(CustomNotifyModule::$booted);
    }

    public function testCustomModuleRegisterInjectsContainerService(): void
    {
        $engine = new Engine(new EngineConfig(
            modules: [ServiceInjectModule::class],
        ));

        $container = $engine->getContainer();
        $this->assertTrue($container->has('module.service'));
        $this->assertSame('pong', $container->get('module.service')->ping());
    }

    public function testGetModuleManagerReturnsSameInstance(): void
    {
        $engine = new Engine(new EngineConfig());

        $this->assertInstanceOf(ModuleManager::class, $engine->getModuleManager());
        $this->assertSame($engine->getModuleManager(), $engine->getModuleManager());
    }
}

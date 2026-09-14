<?php

namespace madong\workflow\tests\unit\modular;

use PHPUnit\Framework\TestCase;
use madong\workflow\modular\ModuleManager;
use madong\workflow\modular\Module;
use madong\workflow\container\Container;
use madong\workflow\exception\FlowException;

class ModuleManagerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testInstallModule(): void
    {
        $manager = new ModuleManager($this->container);
        $module = $this->createModule('test');

        $manager->install($module);

        $this->assertTrue($manager->isInstalled('test'));
        $this->assertContains('test', $manager->getInstalledModules());
    }

    public function testInstallDuplicateModuleThrows(): void
    {
        $manager = new ModuleManager($this->container);
        $manager->install($this->createModule('test'));

        $this->expectException(FlowException::class);

        $manager->install($this->createModule('test'));
    }

    public function testInstallModuleWithMissingDependencyThrows(): void
    {
        $manager = new ModuleManager($this->container);
        $module = $this->createModule('child', ['missing_dep']);

        $this->expectException(FlowException::class);

        $manager->install($module);
    }

    public function testInstallModuleWithSatisfiedDependency(): void
    {
        $manager = new ModuleManager($this->container);
        $parent = $this->createModule('parent');
        $child  = $this->createModule('child', ['parent']);

        $manager->install($parent);
        $manager->install($child);

        $this->assertTrue($manager->isInstalled('parent'));
        $this->assertTrue($manager->isInstalled('child'));
    }

    public function testUninstallModule(): void
    {
        $manager = new ModuleManager($this->container);
        $manager->install($this->createModule('test'));
        $manager->uninstall('test');

        $this->assertFalse($manager->isInstalled('test'));
    }

    public function testUninstallModuleWithDependentThrows(): void
    {
        $manager = new ModuleManager($this->container);
        $manager->install($this->createModule('parent'));
        $manager->install($this->createModule('child', ['parent']));

        $this->expectException(FlowException::class);

        $manager->uninstall('parent');
    }

    public function testBootAllCallsBootInDependencyOrder(): void
    {
        $bootOrder = [];

        $parent = $this->createModule('parent', [], function () use (&$bootOrder) {
            $bootOrder[] = 'parent';
        });
        $child = $this->createModule('child', ['parent'], function () use (&$bootOrder) {
            $bootOrder[] = 'child';
        });

        $manager = new ModuleManager($this->container);
        $manager->install($parent);
        $manager->install($child);
        $manager->bootAll();

        // 依赖拓扑排序：parent 应在 child 之前 boot
        $this->assertEquals(['parent', 'child'], $bootOrder);
    }

    public function testBootAllOnlyOnce(): void
    {
        $bootCount = 0;
        $module = $this->createModule('test', [], function () use (&$bootCount) {
            $bootCount++;
        });

        $manager = new ModuleManager($this->container);
        $manager->install($module);
        $manager->bootAll();
        $manager->bootAll(); // 第二次调用

        $this->assertEquals(1, $bootCount);
    }

    private function createModule(
        string $id,
        array $depends = [],
        ?\Closure $onBoot = null,
    ): Module {
        return new class($id, $depends, $onBoot) implements Module {
            public function __construct(
                private string $id,
                private array $depends,
                private ?\Closure $onBoot,
            ) {}

            public function id(): string { return $this->id; }
            public function depends(): array { return $this->depends; }
            public function register(Container $container): void {}
            public function boot(): void {
                if ($this->onBoot) ($this->onBoot)();
            }
            public function shutdown(): void {}
        };
    }
}

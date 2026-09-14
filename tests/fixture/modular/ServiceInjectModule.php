<?php

namespace madong\workflow\tests\fixture\modular;

use madong\workflow\modular\Module;
use madong\workflow\container\Container;

/**
 * 测试用模块：在 register 中向容器注入服务（供集成测试断言）。
 */
class ServiceInjectModule implements Module
{
    public function id(): string { return 'service_inject'; }
    public function depends(): array { return ['core']; }

    public function register(Container $container): void
    {
        $container->set('module.service', new class {
            public function ping(): string { return 'pong'; }
        });
    }

    public function boot(): void {}
    public function shutdown(): void {}
}

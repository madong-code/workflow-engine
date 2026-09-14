<?php

namespace madong\workflow\tests\fixture\modular;

use madong\workflow\modular\Module;
use madong\workflow\container\Container;

/**
 * 测试用模块：无参构造，记录 register/boot 调用状态（供集成测试断言）。
 */
class CustomNotifyModule implements Module
{
    public static bool $registered = false;
    public static bool $booted = false;
    public static bool $shutdown = false;

    public function id(): string { return 'custom_notify'; }
    public function depends(): array { return ['core']; }

    public function register(Container $container): void
    {
        self::$registered = true;
    }

    public function boot(): void
    {
        self::$booted = true;
    }

    public function shutdown(): void
    {
        self::$shutdown = true;
    }
}

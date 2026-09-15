<?php

namespace madong\workflow\modular\module;

use madong\workflow\modular\Module;
use madong\workflow\container\Container;

class EventModule implements Module
{
    public function id(): string { return 'event'; }
    public function depends(): array { return ['core']; }
    public function register(Container $container): void { /* 事件由 EventBus 管理 */ }
    public function boot(): void {}
    public function shutdown(): void {}
}

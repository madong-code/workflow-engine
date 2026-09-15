<?php

namespace madong\workflow\modular\module;

use madong\workflow\modular\Module;
use madong\workflow\container\Container;

class CoreModule implements Module
{
    public function id(): string { return 'core'; }
    public function depends(): array { return []; }
    public function register(Container $container): void { /* 核心模块无需额外注册 */ }
    public function boot(): void {}
    public function shutdown(): void {}
}

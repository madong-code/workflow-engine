<?php

namespace madong\workflow\modular\module;

use madong\workflow\modular\Module;
use madong\workflow\container\Container;

class InterceptorModule implements Module
{
    public function id(): string { return 'interceptor'; }
    public function depends(): array { return ['core']; }
    public function register(Container $container): void { /* 拦截器由 AOP AspectDispatcher 管理 */ }
    public function boot(): void {}
    public function shutdown(): void {}
}

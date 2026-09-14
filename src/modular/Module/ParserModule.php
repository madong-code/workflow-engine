<?php

namespace madong\workflow\modular\module;

use madong\workflow\modular\Module;
use madong\workflow\container\Container;
use madong\workflow\parser\handler\{
    StartParser, EndParser, TaskParser, DecisionParser,
    ForkParser, JoinParser, CustomParser, SubProcessParser
};

class ParserModule implements Module
{
    public function id(): string { return 'parser'; }
    public function depends(): array { return ['core']; }

    public function register(Container $container): void
    {
        // 解析器由 NodeParserRegistry 管理，不通过容器
    }

    public function boot(): void {}
    public function shutdown(): void {}
}

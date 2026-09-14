<?php

namespace madong\workflow\parser;

interface NodeParserProvider
{
    /**
     * @return array<string, class-string<NodeParser>>
     */
    public function parsers(): array;
}

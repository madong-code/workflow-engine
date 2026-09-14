<?php

namespace madong\workflow\parser;

/**
 * 设计器解析器注册表
 */
class DesignerParserRegistry
{
    /** @var array<string, DesignerParserInterface> */
    private array $parsers = [];

    public function register(DesignerParserInterface $parser): void
    {
        $this->parsers[$parser->getName()] = $parser;
    }

    public function registerBatch(array $parsers): void
    {
        foreach ($parsers as $parser) {
            $this->register($parser);
        }
    }

    public function find(string $name): ?DesignerParserInterface
    {
        return $this->parsers[$name] ?? null;
    }

    public function findByData(array $data): ?DesignerParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($data)) {
                return $parser;
            }
        }
        return null;
    }

    public function has(string $name): bool
    {
        return isset($this->parsers[$name]);
    }

    public function getNames(): array
    {
        return array_keys($this->parsers);
    }
}

<?php

namespace madong\workflow\parser\handler;

use madong\workflow\parser\AbstractNodeParser;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\model\nodes\CustomNode;

class CustomParser extends AbstractNodeParser
{
    protected function parseNode(LfNode $lfNode): void
    {
        $props = $lfNode->getProperties();
        if ($props !== null) {
            $this->builder
                ->clazz($this->getProp($props, 'clazz'))
                ->methodName($this->getProp($props, 'method_name'))
                ->args($this->getProp($props, 'args'))
                ->var($this->getProp($props, 'var'));
        }
    }

    private function getProp(mixed $props, string $key): ?string
    {
        if ($props === null) return null;
        // 统一 snake_case 键读取
        if (is_array($props) || $props instanceof \ArrayAccess) {
            if (isset($props[$key])) return (string) $props[$key];
        } elseif (is_object($props) && property_exists($props, $key)) {
            return (string) $props->$key;
        }
        return null;
    }

    public function build(): CustomNode
    {
        return $this->builder->buildCustomNode();
    }
}

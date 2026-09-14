<?php

namespace madong\workflow\parser;

use madong\workflow\model\NodeModel;
use madong\workflow\model\TransitionModel;
use madong\workflow\model\vo\{Layout, TransitionPath};
use madong\workflow\model\builder\NodeBuilder;
use madong\workflow\parser\lf\LfNode;
use madong\workflow\parser\lf\LfEdge;

abstract class AbstractNodeParser implements NodeParser
{
    protected NodeBuilder $builder;

    public function parse(LfNode $lfNode, array $edges): void
    {
        $this->builder = new NodeBuilder();

        $this->builder
            ->name($lfNode->getId())
            ->displayName($this->extractDisplayName($lfNode))
            ->layout($this->parseLayout($lfNode))
            ->preInterceptors($this->getProperty($lfNode, 'pre_interceptors'))
            ->postInterceptors($this->getProperty($lfNode, 'post_interceptors'));

        $outputs = $this->parseOutputEdges($lfNode, $edges);
        $this->builder->outputs($outputs);

        $this->parseNode($lfNode);
    }

    abstract protected function parseNode(LfNode $lfNode): void;

    protected function extractDisplayName(LfNode $lfNode): string
    {
        $text = $lfNode->getText();
        if (!empty($text)) return $text;

        $props = $lfNode->getProperties();
        if ($props !== null) {
            if (is_array($props) || $props instanceof \ArrayAccess) {
                if (isset($props['display_name'])) return (string) $props['display_name'];
            } elseif (is_object($props) && isset($props->display_name)) {
                return (string) $props->display_name;
            }
        }

        return $lfNode->getId();
    }

    protected function parseLayout(LfNode $lfNode): Layout
    {
        return new Layout($lfNode->x, $lfNode->y, 0, 0);
    }

    protected function getProperty(LfNode $lfNode, string $key): mixed
    {
        $props = $lfNode->getProperties();
        if ($props === null) return null;

        if (is_array($props) || $props instanceof \ArrayAccess) {
            return $props[$key] ?? null;
        }
        if (is_object($props)) {
            return $props->$key ?? null;
        }
        return null;
    }

    protected function getStringProperty(LfNode $lfNode, string $key): ?string
    {
        $v = $this->getProperty($lfNode, $key);
        return $v !== null ? (string) $v : null;
    }

    /**
     * @return TransitionModel[]
     */
    protected function parseOutputEdges(LfNode $lfNode, array $edges): array
    {
        $outputs = [];
        foreach ($edges as $edge) {
            if ($edge->getSourceNodeId() === $lfNode->getId()) {
                $expr = null;
                $edgeProps = $edge->getProperties();
                if ($edgeProps !== null) {
                    if (is_array($edgeProps) || $edgeProps instanceof \ArrayAccess) {
                        $expr = $edgeProps['expr'] ?? null;
                    } elseif (is_object($edgeProps)) {
                        $expr = $edgeProps->expr ?? null;
                    }
                }

                $outputs[] = new TransitionModel(
                    name: $edge->getId(),
                    expr: $expr !== null ? (string) $expr : null,
                    to: $edge->getTargetNodeId(),
                );
            }
        }
        return $outputs;
    }
}

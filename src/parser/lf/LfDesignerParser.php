<?php

namespace madong\workflow\parser\lf;

use madong\workflow\parser\DesignerParserInterface;

/**
 * LogicFlow 设计器解析器
 */
class LfDesignerParser implements DesignerParserInterface
{
    public function getName(): string
    {
        return 'logicflow';
    }

    public function supports(array $data): bool
    {
        return isset($data['nodes']) && isset($data['edges']);
    }

    public function parse(array $data): LfModel
    {
        $nodes = [];
        foreach ($data['nodes'] ?? [] as $n) {
            $text = $n['text']['value'] ?? ($n['text'] ?? null);
            $props = $n['properties'] ?? null;
            if ($props !== null) {
                $props = (object) $props;
            }
            $nodes[] = new LfNode(
                id: $n['id'],
                type: $n['type'],
                x: (float) ($n['x'] ?? 0),
                y: (float) ($n['y'] ?? 0),
                text: is_string($text) ? $text : null,
                properties: $props,
            );
        }

        $edges = [];
        foreach ($data['edges'] ?? [] as $e) {
            $edgeText = $e['text']['value'] ?? ($e['text'] ?? null);
            $edgeProps = $e['properties'] ?? null;
            if ($edgeProps !== null) {
                $edgeProps = (object) $edgeProps;
            }
            $edges[] = new LfEdge(
                id: $e['id'],
                sourceNodeId: $e['sourceNodeId'],
                targetNodeId: $e['targetNodeId'],
                type: $e['type'] ?? null,
                text: is_string($edgeText) ? $edgeText : null,
                properties: $edgeProps,
            );
        }

        return new LfModel(
            name: $data['name'] ?? '',
            display_name: $data['display_name'] ?? '',
            type: $data['type'] ?? '',
            instance_url: $data['instance_url'] ?? '',
            expire_time: $data['expire_time'] ?? '',
            instance_no_class: $data['instance_no_class'] ?? '',
            pre_interceptors: $data['pre_interceptors'] ?? null,
            post_interceptors: $data['post_interceptors'] ?? null,
            nodes: $nodes,
            edges: $edges,
        );
    }
}

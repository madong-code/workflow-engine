<?php

namespace madong\workflow\parser;

use madong\workflow\model\ProcessModel;
use madong\workflow\model\NodeModel;
use madong\workflow\model\builder\ProcessModelBuilder;
use madong\workflow\parser\lf\{LfNode, LfEdge, LfModel};

class ModelParser
{
    public function __construct(
        private readonly NodeParserRegistry $registry,
        private readonly ?DesignerParserRegistry $designerRegistry = null,
    ) {}

    public function parse(string|array $input): ProcessModel
    {
        $data = is_string($input) ? json_decode($input, true) : $input;
        $lfModel = $this->parseLfModel($data);

        $builder = (new ProcessModelBuilder())
            ->name($lfModel->name)
            ->displayName($lfModel->getDisplayName())
            ->type($lfModel->type)
            ->instanceUrl($lfModel->getInstanceUrl())
            ->expireTime($lfModel->getExpireTime())
            ->instanceNoClass($lfModel->getInstanceNoClass())
            ->preInterceptors($lfModel->getPreInterceptors())
            ->postInterceptors($lfModel->getPostInterceptors());

        $edges = $this->buildLfEdges($lfModel);
        foreach ($lfModel->nodes as $lfNode) {
            $type = str_replace(NodeParser::NODE_TYPE_PREFIX, '', $lfNode->type);
            $parser = $this->registry->find($type);
            if ($parser !== null) {
                $parser->parse($lfNode, $edges);
                $builder->addNode($parser->build());
            }
        }

        $processModel = $builder->build();
        $this->connectTransitions($processModel);

        return $processModel;
    }

    private function parseLfModel(array $data): LfModel
    {
        if ($this->designerRegistry !== null) {
            $designerParser = $this->designerRegistry->findByData($data);
            if ($designerParser !== null) {
                return $designerParser->parse($data);
            }
        }

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

    private function buildLfEdges(LfModel $lfModel): array
    {
        return $lfModel->edges;
    }

    private function connectTransitions(ProcessModel $model): void
    {
        foreach ($model->getNodes() as $node) {
            foreach ($node->outputs as $transition) {
                $target = $model->getNode($transition->to);
                if ($target !== null) {
                    $transition->target = $target;
                    $transition->source = $node;
                    $target->inputs[] = $transition;
                }
            }
        }
    }
}
<?php

namespace madong\workflow\tests\fixture\memory;

use madong\workflow\interface\services\IProcessDefineService;
use madong\workflow\interface\model\IProcessDefine;
use madong\workflow\model\ProcessModel;
use madong\workflow\parser\{ModelParser, NodeParserRegistry, DesignerParserRegistry};
use madong\workflow\parser\handler\{
    StartParser, EndParser, TaskParser, DecisionParser,
    ForkParser, JoinParser, CustomParser, SubProcessParser
};
use madong\workflow\parser\lf\LfDesignerParser;

class MemoryProcessDefineService implements IProcessDefineService
{
    private array $defines = [];
    private int $autoId = 1;
    private ?NodeParserRegistry $registry = null;
    private ?DesignerParserRegistry $designerRegistry = null;

    private function getRegistry(): NodeParserRegistry
    {
        if ($this->registry === null) {
            $this->registry = new NodeParserRegistry();
            $this->registry->registerBatch([
                'start'        => StartParser::class,
                'end'          => EndParser::class,
                'task'         => TaskParser::class,
                'decision'     => DecisionParser::class,
                'fork'         => ForkParser::class,
                'join'         => JoinParser::class,
                'custom'       => CustomParser::class,
                'wfSubProcess' => SubProcessParser::class,
            ]);
        }
        return $this->registry;
    }

    private function getDesignerRegistry(): DesignerParserRegistry
    {
        if ($this->designerRegistry === null) {
            $this->designerRegistry = new DesignerParserRegistry();
            $this->designerRegistry->register(new LfDesignerParser());
        }
        return $this->designerRegistry;
    }

    public function createProcessDefine(array $data): IProcessDefine
    {
        $id = $data['id'] ?? (string) $this->autoId++;
        $define = MemoryDict::of(array_merge($data, ['id' => $id]));
        $this->defines[$id] = $define;
        return $define;
    }

    public function updateProcessDefine(string $id, array $data): IProcessDefine
    {
        $define = $this->defines[$id] ?? null;
        if ($define === null) {
            throw new \RuntimeException("流程定义 [{$id}] 不存在");
        }
        foreach ($data as $k => $v) {
            $define->put($k, $v);
        }
        return $define;
    }

    public function getById(string $id): ?IProcessDefine
    {
        return $this->defines[$id] ?? null;
    }

    public function getProcessModel(string $defineId): ?ProcessModel
    {
        $define = $this->getById($defineId);
        if ($define === null) return null;
        $content = $define->getData('content');
        if (empty($content)) return null;

        $parser = new ModelParser($this->getRegistry(), $this->getDesignerRegistry());
        return $parser->parse($content);
    }

    public function getProcessDefineByVersion(string $name, int $version): ?IProcessDefine
    {
        foreach ($this->defines as $define) {
            if ($define->getData('name') === $name) {
                return $define;
            }
        }
        return null;
    }

    public function deleteProcessDefine(string $id): void
    {
        unset($this->defines[$id]);
    }

    public function publishProcessDefine(string $id): IProcessDefine
    {
        return $this->updateProcessDefine($id, ['state' => 1]);
    }

    public function disableProcessDefine(string $id): IProcessDefine
    {
        return $this->updateProcessDefine($id, ['state' => 2]);
    }
}

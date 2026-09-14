<?php

namespace madong\workflow\handler;

use madong\workflow\context\ExecutionContext;
use madong\workflow\model\nodes\SubProcessNode;
use madong\workflow\exception\DefinitionException;

class StartSubProcessHandler implements Handler
{
    public function __construct(
        private readonly SubProcessNode $subProcessNode,
    ) {}

    public function handle(ExecutionContext $ctx): void
    {
        $defineService = $ctx->engine->getService(
            \madong\workflow\interface\services\IProcessDefineService::class
        );

        $processDefine = $defineService->getProcessDefineByVersion(
            $this->subProcessNode->processDefineId ?? $this->subProcessNode->name,
            $this->subProcessNode->version
        );

        if ($processDefine === null) {
            throw DefinitionException::notFound(
                $this->subProcessNode->processDefineId ?? $this->subProcessNode->name
            );
        }

        $instance = $ctx->engine->startProcess(
            $processDefine->getId(),
            $ctx->operator,
            $ctx->variables->getAll(),
            $ctx->processInstanceId,
            $this->subProcessNode->name,
        );

        $ctx->addTasks([$instance]);
    }
}

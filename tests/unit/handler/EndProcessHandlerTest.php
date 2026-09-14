<?php

namespace madong\workflow\tests\unit\handler;

use PHPUnit\Framework\TestCase;
use madong\workflow\handler\EndProcessHandler;
use madong\workflow\context\{ExecutionContext, ProcessVariables, ExecutionResult};
use madong\workflow\Engine;
use madong\workflow\interface\services\IProcessInstanceService;
use madong\workflow\interface\model\IProcessInstance;
use madong\workflow\enum\{ProcessState, SubmitType, ProcessConst};

class EndProcessHandlerTest extends TestCase
{
    public function testHandleFinishesProcessOnAgree(): void
    {
        $instanceService = $this->createMock(IProcessInstanceService::class);
        $instanceService->expects($this->once())
            ->method('finishProcessInstance')
            ->with('inst-1');
        $instanceService->expects($this->never())
            ->method('rejectProcessInstance');

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->with(IProcessInstanceService::class)
            ->willReturn($instanceService);
        $engine->method('getAspectDispatcher')->willReturn(
            $this->createMock(\madong\workflow\aop\AspectDispatcher::class)
        );
        $engine->method('getEventBus')->willReturn(
            $this->createMock(\madong\workflow\event\EventBus::class)
        );

        $variables = new ProcessVariables();
        $variables->set(ProcessConst::SUBMIT_TYPE, SubmitType::AGREE->value);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user001',
            engine: $engine,
            variables: $variables,
        );

        $handler = new EndProcessHandler();
        $handler->handle($ctx);
    }

    public function testHandleRejectsProcessOnReject(): void
    {
        $instanceService = $this->createMock(IProcessInstanceService::class);
        $instanceService->expects($this->never())
            ->method('finishProcessInstance');
        $instanceService->expects($this->once())
            ->method('rejectProcessInstance')
            ->with('inst-1');

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->with(IProcessInstanceService::class)
            ->willReturn($instanceService);
        $engine->method('getAspectDispatcher')->willReturn(
            $this->createMock(\madong\workflow\aop\AspectDispatcher::class)
        );
        $engine->method('getEventBus')->willReturn(
            $this->createMock(\madong\workflow\event\EventBus::class)
        );

        $variables = new ProcessVariables();
        $variables->set(ProcessConst::SUBMIT_TYPE, SubmitType::REJECT->value);

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user001',
            engine: $engine,
            variables: $variables,
        );

        $handler = new EndProcessHandler();
        $handler->handle($ctx);
    }

    public function testHandleDefaultsToFinishWhenNoSubmitType(): void
    {
        $instanceService = $this->createMock(IProcessInstanceService::class);
        $instanceService->expects($this->once())
            ->method('finishProcessInstance')
            ->with('inst-1');

        $engine = $this->createMock(Engine::class);
        $engine->method('getService')
            ->with(IProcessInstanceService::class)
            ->willReturn($instanceService);
        $engine->method('getAspectDispatcher')->willReturn(
            $this->createMock(\madong\workflow\aop\AspectDispatcher::class)
        );
        $engine->method('getEventBus')->willReturn(
            $this->createMock(\madong\workflow\event\EventBus::class)
        );

        $ctx = new ExecutionContext(
            processInstanceId: 'inst-1',
            processTaskId: '1',
            operator: 'user001',
            engine: $engine,
        );

        $handler = new EndProcessHandler();
        $handler->handle($ctx);
    }
}

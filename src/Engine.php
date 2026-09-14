<?php

namespace madong\workflow;

use madong\workflow\config\EngineConfig;
use madong\workflow\container\Container;
use madong\workflow\context\{ExecutionContext, ProcessVariables, ExecutionResult, DefaultNoGenerator};
use madong\workflow\aop\{AspectDispatcher, AspectTrace};
use madong\workflow\event\EventBus;
use madong\workflow\expression\{ExpressionEngine, DefaultExpressionEngine};
use madong\workflow\parser\{NodeParserRegistry, ModelParser, DesignerParserRegistry};
use madong\workflow\parser\handler\{
    StartParser, EndParser, TaskParser, DecisionParser,
    ForkParser, JoinParser, CustomParser, SubProcessParser
};
use madong\workflow\parser\lf\LfDesignerParser;
use madong\workflow\modular\{ModuleManager};
use madong\workflow\modular\module\{CoreModule, ParserModule, EventModule, InterceptorModule};
use madong\workflow\enum\{ProcessState, TaskState, ProcessConst, SubmitType};
use madong\workflow\exception\{DefinitionException, StateException, PermissionException, NodeException};
use madong\workflow\libs\Dict;

class Engine
{
    private Container $container;
    private AspectDispatcher $aspectDispatcher;
    private EventBus $eventBus;
    private ExpressionEngine $expressionEngine;
    private NodeParserRegistry $parserRegistry;
    private DesignerParserRegistry $designerRegistry;
    private ModuleManager $moduleManager;
    private DefaultNoGenerator $noGenerator;

    public function __construct(EngineConfig $config)
    {
        if ($config->container !== null) {
            $this->container = $config->container;
            if (method_exists($this->container, 'setBatch') && !empty($config->services)) {
                $this->container->setBatch($config->services);
            } elseif (method_exists($this->container, 'set')) {
                foreach ($config->services as $name => $service) {
                    $this->container->set($name, $service);
                }
            }
        } else {
            $this->container = new Container($config->services, $config->extensions);
        }

        $this->aspectDispatcher = new AspectDispatcher();
        $this->aspectDispatcher->setTraceEnabled($config->debug);
        foreach ($config->aspects as $aspectClass) {
            $this->aspectDispatcher->register(new $aspectClass());
        }

        $this->eventBus = new EventBus();
        $this->expressionEngine = new DefaultExpressionEngine();

        $this->parserRegistry = new NodeParserRegistry();
        $this->parserRegistry->registerBatch([
            'start'        => StartParser::class,
            'end'          => EndParser::class,
            'task'         => TaskParser::class,
            'decision'     => DecisionParser::class,
            'fork'         => ForkParser::class,
            'join'         => JoinParser::class,
            'custom'       => CustomParser::class,
            'wfSubProcess' => SubProcessParser::class,
        ]);

        $this->designerRegistry = new DesignerParserRegistry();
        $this->designerRegistry->register(new LfDesignerParser());

        $this->moduleManager = new ModuleManager($this->container);
        $this->moduleManager->install(new CoreModule());
        $this->moduleManager->install(new ParserModule());
        $this->moduleManager->install(new EventModule());
        $this->moduleManager->install(new InterceptorModule());
        foreach ($config->modules as $moduleClass) {
            $this->moduleManager->install(new $moduleClass());
        }
        $this->moduleManager->bootAll();

        $this->noGenerator = new DefaultNoGenerator();
    }

    public function startProcess(
        string $definitionId,
        string $operator,
        array  $vars = [],
        ?string $parentId = null,
        ?string $parentNodeName = null,
    ): object {
        $defineService = $this->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $processDefine = $defineService->getById($definitionId);
        if ($processDefine === null) {
            throw new DefinitionException("流程定义 [{$definitionId}] 不存在");
        }

        $processModel = $defineService->getProcessModel($definitionId);
        if ($processModel === null) {
            throw new DefinitionException("流程定义 [{$definitionId}] 解析失败");
        }

        $startNode = $processModel->getStartNode();
        if ($startNode === null) {
            throw new DefinitionException("流程定义 [{$definitionId}] 缺少开始节点");
        }

        $instanceService = $this->getService(\madong\workflow\interface\services\IProcessInstanceService::class);
        $processInstance = $instanceService->createProcessInstance(
            $processDefine, $operator, Dict::of($vars), $parentId, $parentNodeName
        );

        $historyService = $this->getService(\madong\workflow\interface\services\IProcessInstanceHistoryService::class);
        if ($historyService !== null) {
            $historyService->createHistoryProcessInstance($processInstance);
        }

        $variables = new ProcessVariables();
        $variables->mergeGlobal($vars);

        $ctx = new ExecutionContext(
            processInstanceId: $processInstance->getId(),
            processTaskId: 0,
            operator: $operator,
            engine: $this,
            processInstance: $processInstance,
            processModel: $processModel,
            variables: $variables,
        );

        AspectTrace::setCurrentProcessId($processInstance->getId());
        try {
            $startNode->execute($ctx);
            return $processInstance;
        } finally {
            AspectTrace::setCurrentProcessId(null);
        }
    }

    public function completeTask(string $taskId, string $operator, array $vars = []): array
    {
        $taskService = $this->getService(\madong\workflow\interface\services\IProcessTaskService::class);

        $task = $taskService->getById($taskId);
        if ($task === null) {
            throw new StateException("任务 [{$taskId}] 不存在");
        }

        $taskService->finishProcessTask($taskId, $operator, Dict::of($vars));

        $instanceService = $this->getService(\madong\workflow\interface\services\IProcessInstanceService::class);
        $processInstance = $instanceService->getById($task->getProcessInstanceId());

        // 办理提交的 vars 持久化到实例变量：保证轨迹/表单回显、后续节点条件判断能读到最新值
        if (!empty($vars)) {
            $instanceService->addVariable($processInstance->getId(), $vars);
        }

        $defineService = $this->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $processModel = $defineService->getProcessModel($processInstance->getProcessDefineId());

        $variables = new ProcessVariables();
        $variables->mergeGlobal($processInstance->getVariable());
        $variables->mergeGlobal($vars);

        $ctx = new ExecutionContext(
            processInstanceId: $processInstance->getId(),
            processTaskId: $taskId,
            operator: $operator,
            engine: $this,
            processInstance: $processInstance,
            processTask: $task,
            processModel: $processModel,
            variables: $variables,
        );

        AspectTrace::setCurrentProcessId($processInstance->getId());
        try {
            $node = $processModel->getNode($task->getTaskName());
            if ($node !== null) {
                $node->execute($ctx);
            }
            return $ctx->getCreatedTasks();
        } finally {
            AspectTrace::setCurrentProcessId(null);
        }
    }

    public function jumpTask(string $taskId, string $operator, string $targetNodeName, array $vars = []): array
    {
        $taskService = $this->getService(\madong\workflow\interface\services\IProcessTaskService::class);
        $task = $taskService->getById($taskId);
        if ($task === null) {
            throw new StateException("任务 [{$taskId}] 不存在");
        }

        $taskService->finishProcessTask($taskId, $operator, Dict::of($vars));

        $instanceService = $this->getService(\madong\workflow\interface\services\IProcessInstanceService::class);
        $processInstance = $instanceService->getById($task->getProcessInstanceId());

        // 退回/跳转离开当前节点：清理同节点残留 DOING 兄弟任务（会签其他成员等），避免幽灵待办
        $taskService->cancelDoingTasksByNode($processInstance->getId(), $task->getTaskName(), $taskId, $operator);

        // 退回/跳转提交的 vars 同样持久化到实例变量
        if (!empty($vars)) {
            $instanceService->addVariable($processInstance->getId(), $vars);
        }

        $defineService = $this->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $processModel = $defineService->getProcessModel($processInstance->getProcessDefineId());

        $variables = new ProcessVariables();
        $variables->mergeGlobal($processInstance->getVariable());
        $variables->mergeGlobal($vars);

        $ctx = new ExecutionContext(
            processInstanceId: $processInstance->getId(),
            processTaskId: $taskId,
            operator: $operator,
            engine: $this,
            processInstance: $processInstance,
            processModel: $processModel,
            variables: $variables,
        );

        $node = $processModel->getNode($targetNodeName);
        if ($node === null) {
            throw new NodeException("目标节点 [{$targetNodeName}] 不存在");
        }

        AspectTrace::setCurrentProcessId($processInstance->getId());
        try {
            $node->execute($ctx);
            return $ctx->getCreatedTasks();
        } finally {
            AspectTrace::setCurrentProcessId(null);
        }
    }

    public function rejectToFirst(string $taskId, string $operator, array $vars = []): array
    {
        $taskService = $this->getService(\madong\workflow\interface\services\IProcessTaskService::class);
        $task = $taskService->getById($taskId);
        if ($task === null) {
            throw new StateException("任务 [{$taskId}] 不存在");
        }

        $instanceService = $this->getService(\madong\workflow\interface\services\IProcessInstanceService::class);
        $processInstance = $instanceService->getById($task->getProcessInstanceId());

        $defineService = $this->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $processModel = $defineService->getProcessModel($processInstance->getProcessDefineId());

        $firstTask = null;
        foreach ($processModel->getTasks() as $taskNode) {
            $firstTask = $taskNode;
            break;
        }

        if ($firstTask === null) {
            throw new StateException("流程定义中无任务节点");
        }

        return $this->jumpTask($taskId, $operator, $firstTask->name, $vars);
    }

    public function rejectToEnd(string $taskId, string $operator): array
    {
        $taskService = $this->getService(\madong\workflow\interface\services\IProcessTaskService::class);
        $task = $taskService->getById($taskId);
        if ($task === null) {
            throw new StateException("任务 [{$taskId}] 不存在");
        }

        $taskService->finishProcessTask($taskId, $operator, Dict::of([
            ProcessConst::SUBMIT_TYPE => SubmitType::REJECT->value,
        ]));

        $instanceService = $this->getService(\madong\workflow\interface\services\IProcessInstanceService::class);
        $processInstance = $instanceService->getById($task->getProcessInstanceId());

        // 拒绝终止流程：清理同节点残留 DOING 兄弟任务（会签其他成员等），避免幽灵待办
        $taskService->cancelDoingTasksByNode($processInstance->getId(), $task->getTaskName(), $taskId, $operator);

        $defineService = $this->getService(\madong\workflow\interface\services\IProcessDefineService::class);
        $processModel = $defineService->getProcessModel($processInstance->getProcessDefineId());

        $endNode = $processModel->getEndNode();
        if ($endNode === null) {
            throw new NodeException("流程定义中没有结束节点");
        }

        $variables = new ProcessVariables();
        $variables->mergeGlobal($processInstance->getVariable());
        $variables->mergeGlobal([ProcessConst::SUBMIT_TYPE => SubmitType::REJECT->value]);

        $ctx = new ExecutionContext(
            processInstanceId: $processInstance->getId(),
            processTaskId: $taskId,
            operator: $operator,
            engine: $this,
            processInstance: $processInstance,
            processTask: $task,
            processModel: $processModel,
            variables: $variables,
        );

        AspectTrace::setCurrentProcessId($processInstance->getId());
        try {
            $endNode->execute($ctx);
            return $ctx->getCreatedTasks();
        } finally {
            AspectTrace::setCurrentProcessId(null);
        }
    }

    public function getService(string $interface): ?object
    {
        return $this->container->get($interface);
    }

    public function getAspectDispatcher(): AspectDispatcher
    {
        return $this->aspectDispatcher;
    }

    public function getEventBus(): EventBus
    {
        return $this->eventBus;
    }

    public function getExpressionEngine(): ExpressionEngine
    {
        return $this->expressionEngine;
    }

    public function getParserRegistry(): NodeParserRegistry
    {
        return $this->parserRegistry;
    }

    public function getDesignerRegistry(): DesignerParserRegistry
    {
        return $this->designerRegistry;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getModuleManager(): ModuleManager
    {
        return $this->moduleManager;
    }
}
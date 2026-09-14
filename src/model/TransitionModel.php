<?php

namespace madong\workflow\model;

use madong\workflow\model\vo\TransitionPath;

final class TransitionModel extends BaseModel
{
    public function __construct(
        string $name = '',
        public readonly ?string $expr = null,
        public readonly ?TransitionPath $path = null,
        public ?NodeModel $source = null,
        public ?NodeModel $target = null,
        public readonly string $to = '',
        public bool $enabled = false,
    ) {
        parent::__construct($name);
    }

    /**
     * 执行转移 — 遍历目标节点的输出边
     */
    public function execute(\madong\workflow\context\ExecutionContext $ctx): void
    {
        if ($this->target !== null) {
            $this->target->execute($ctx);
        }
    }

    /**
     * 评估表达式决定是否启用
     */
    public function evaluateEnabled(\madong\workflow\context\ExecutionContext $ctx): bool
    {
        if ($this->expr === null || $this->expr === '') {
            $this->enabled = true;
            return true;
        }

        $engine = $ctx->engine ?? null;
        if ($engine !== null) {
            $expressionEngine = $engine->getExpressionEngine();
            $this->enabled = $expressionEngine->evaluateToBool($this->expr, $ctx->variables);
        } else {
            $this->enabled = true;
        }

        return $this->enabled;
    }
}

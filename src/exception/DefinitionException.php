<?php

namespace madong\workflow\exception;

class DefinitionException extends FlowException
{
    public int $statusCode = 404;
    public int $errorCode = 1001;
    public string $errorMessage = '流程定义错误';
}
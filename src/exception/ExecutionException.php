<?php

namespace madong\workflow\exception;

class ExecutionException extends FlowException
{
    public int $statusCode = 500;
    public int $errorCode = 1005;
    public string $errorMessage = '执行错误';
}
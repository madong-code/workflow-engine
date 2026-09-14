<?php

namespace madong\workflow\exception;

class StateException extends FlowException
{
    public int $statusCode = 400;
    public int $errorCode = 1002;
    public string $errorMessage = '状态错误';
}
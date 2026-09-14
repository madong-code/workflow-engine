<?php

namespace madong\workflow\exception;

class NodeException extends FlowException
{
    public int $statusCode = 400;
    public int $errorCode = 1003;
    public string $errorMessage = '节点错误';
}
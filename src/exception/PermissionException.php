<?php

namespace madong\workflow\exception;

class PermissionException extends FlowException
{
    public int $statusCode = 403;
    public int $errorCode = 1004;
    public string $errorMessage = '权限错误';
}
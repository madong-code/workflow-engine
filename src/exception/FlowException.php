<?php

namespace madong\workflow\exception;

class FlowException extends \Exception
{
    public int $statusCode = 400;
    public array $header = [];
    public int $errorCode = 0;
    public string $errorMessage = '';
    public mixed $data = null;
    public string $error = '';

    public function __construct(
        string $errorMessage = '',
        array $params = [],
        string $error = ''
    ) {
        $this->errorMessage = $errorMessage ?: $this->errorMessage;
        $this->error = $error;
        parent::__construct($this->errorMessage);

        if (!empty($params)) {
            $this->statusCode = $params['statusCode'] ?? $this->statusCode;
            $this->header = array_merge($this->header, $params['header'] ?? []);
            $this->errorCode = $params['errorCode'] ?? $this->errorCode;
            $this->data = $params['data'] ?? $this->data;
        }
    }
}
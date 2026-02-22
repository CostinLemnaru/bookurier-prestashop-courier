<?php

namespace Bookurier\Awb;

class AwbGenerationException extends \RuntimeException
{
    private $requestPayload;
    private $responsePayload;

    public function __construct(
        $message = '',
        $requestPayload = '',
        $responsePayload = '',
        $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct((string) $message, (int) $code, $previous);
        $this->requestPayload = (string) $requestPayload;
        $this->responsePayload = (string) $responsePayload;
    }

    public function getRequestPayload()
    {
        return $this->requestPayload;
    }

    public function getResponsePayload()
    {
        return $this->responsePayload;
    }
}

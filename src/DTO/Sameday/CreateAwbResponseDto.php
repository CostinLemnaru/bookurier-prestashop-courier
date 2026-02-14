<?php
/**
 * DTO for SameDay AWB creation response.
 */

namespace Bookurier\DTO\Sameday;

class CreateAwbResponseDto
{
    /**
     * @var bool
     */
    private $success;

    /**
     * @var string
     */
    private $awbNumber;

    /**
     * @var string
     */
    private $message;

    /**
     * @var array<string, mixed>
     */
    private $rawResponse;

    /**
     * @param bool $success
     * @param string $awbNumber
     * @param string $message
     * @param array<string, mixed> $rawResponse
     */
    public function __construct($success, $awbNumber, $message, array $rawResponse = array())
    {
        $this->success = (bool) $success;
        $this->awbNumber = (string) $awbNumber;
        $this->message = (string) $message;
        $this->rawResponse = $rawResponse;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return self
     */
    public static function fromApiResponse(array $response)
    {
        $awbNumber = isset($response['awbNumber']) ? (string) $response['awbNumber'] : '';
        $message = '';

        if (isset($response['message'])) {
            $message = (string) $response['message'];
        } elseif (isset($response['error']['message'])) {
            $message = (string) $response['error']['message'];
        }

        $success = $awbNumber !== '';
        if (isset($response['status']) && strtolower((string) $response['status']) === 'error') {
            $success = false;
        }

        return new self($success, $awbNumber, $message, $response);
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @return string
     */
    public function getAwbNumber()
    {
        return $this->awbNumber;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }
}


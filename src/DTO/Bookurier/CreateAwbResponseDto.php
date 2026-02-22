<?php
/**
 * DTO for Bookurier AWB creation response.
 */

namespace Bookurier\DTO\Bookurier;

class CreateAwbResponseDto
{
    /**
     * @var bool
     */
    private $success;

    /**
     * @var array<int, string>
     */
    private $awbCodes;

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
     * @param array<int, string> $awbCodes
     * @param string $message
     * @param array<string, mixed> $rawResponse
     */
    public function __construct($success, array $awbCodes, $message, array $rawResponse = array())
    {
        $this->success = (bool) $success;
        $this->awbCodes = $awbCodes;
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
        $status = isset($response['status']) ? strtolower((string) $response['status']) : '';
        $success = $status === 'success';
        $message = isset($response['message']) ? (string) $response['message'] : '';

        $awbCodes = array();
        if (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $value) {
                $awbCodes[] = (string) $value;
            }
        }

        return new self($success, $awbCodes, $message, $response);
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @return array<int, string>
     */
    public function getAwbCodes()
    {
        return $this->awbCodes;
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


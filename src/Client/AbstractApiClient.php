<?php
/**
 * Shared HTTP helpers for external API clients.
 */

namespace Bookurier\Client;

use Bookurier\Exception\ApiException;
use Bookurier\Logging\LoggerInterface;
use Bookurier\Logging\NullLogger;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractApiClient
{
    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ClientInterface|null $httpClient
     * @param LoggerInterface|null $logger
     *
     * @return void
     */
    protected function initializeHttpClient(ClientInterface $httpClient = null, LoggerInterface $logger = null)
    {
        $this->httpClient = $httpClient ?: new Client(array(
            'timeout' => 30,
            'connect_timeout' => 30,
            'http_errors' => false,
            'verify' => true,
        ));
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param string $providerName
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     *
     * @return ResponseInterface
     *
     * @throws ApiException
     */
    protected function requestOrFail($providerName, $method, $url, array $options = array())
    {
        try {
            return $this->httpClient->request((string) $method, (string) $url, $options);
        } catch (GuzzleException $e) {
            throw new ApiException(
                (string) $providerName . ' HTTP request failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param string $providerName
     * @param ResponseInterface $response
     * @param string $endpoint
     * @param bool $includeApiMessage
     *
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    protected function decodeJsonOrFail($providerName, ResponseInterface $response, $endpoint, $includeApiMessage = true)
    {
        $statusCode = (int) $response->getStatusCode();
        $decodedBody = json_decode((string) $response->getBody(), true);

        if ($statusCode >= 400) {
            $message = '';
            if ($includeApiMessage && is_array($decodedBody)) {
                $message = $this->extractApiErrorMessage($decodedBody);
            }

            throw new ApiException(
                (string) $providerName . ' request to ' . (string) $endpoint . ' failed with HTTP ' . $statusCode
                . ($message !== '' ? ': ' . $message : '.')
            );
        }

        if (!is_array($decodedBody)) {
            throw new ApiException(
                (string) $providerName . ' response from ' . (string) $endpoint . ' is not valid JSON.'
            );
        }

        return $decodedBody;
    }

    /**
     * @param array<string, mixed> $decodedBody
     *
     * @return string
     */
    protected function extractApiErrorMessage(array $decodedBody)
    {
        if (isset($decodedBody['error']['message'])) {
            return (string) $decodedBody['error']['message'];
        }

        if (isset($decodedBody['message'])) {
            return (string) $decodedBody['message'];
        }

        return '';
    }
}


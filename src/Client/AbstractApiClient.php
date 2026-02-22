<?php
/**
 * Shared HTTP helpers for external API clients.
 */

namespace Bookurier\Client;

use Bookurier\Exception\ApiException;
use Bookurier\Client\Http\SimpleHttpResponse;
use Bookurier\Logging\LoggerInterface;
use Bookurier\Logging\NullLogger;

abstract class AbstractApiClient
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface|null $logger
     *
     * @return void
     */
    protected function initializeHttpClient(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param string $providerName
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     *
     * @return SimpleHttpResponse
     *
     * @throws ApiException
     */
    protected function requestOrFail($providerName, $method, $url, array $options = array())
    {
        return $this->sendCurlRequest($providerName, $method, $url, $options);
    }

    /**
     * @param string $providerName
     * @param SimpleHttpResponse $response
     * @param string $endpoint
     * @param bool $includeApiMessage
     *
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    protected function decodeJsonOrFail($providerName, SimpleHttpResponse $response, $endpoint, $includeApiMessage = true)
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

    /**
     * @param string $providerName
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     *
     * @return SimpleHttpResponse
     *
     * @throws ApiException
     */
    private function sendCurlRequest($providerName, $method, $url, array $options = array())
    {
        if (!function_exists('curl_init')) {
            throw new ApiException((string) $providerName . ' HTTP client is not available (cURL extension missing).');
        }

        $method = strtoupper((string) $method);
        $url = $this->appendQueryString((string) $url, isset($options['query']) && is_array($options['query']) ? $options['query'] : array());

        $curl = curl_init($url);
        if ($curl === false) {
            throw new ApiException((string) $providerName . ' HTTP request could not be initialized.');
        }

        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 30;
        $connectTimeout = isset($options['connect_timeout']) ? (int) $options['connect_timeout'] : 30;
        $verify = !isset($options['verify']) || (bool) $options['verify'];

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

        $headers = $this->normalizeHeaders(isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : array());
        $body = $this->resolveRequestBody($options, $headers);

        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($curl);
        if ($rawBody === false) {
            $error = curl_error($curl);
            curl_close($curl);

            throw new ApiException((string) $providerName . ' HTTP request failed: ' . (string) $error);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return new SimpleHttpResponse($statusCode, (string) $rawBody);
    }

    /**
     * @param string $url
     * @param array<string, mixed> $query
     *
     * @return string
     */
    private function appendQueryString($url, array $query)
    {
        if (empty($query)) {
            return $url;
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
    }

    /**
     * @param array<int|string, mixed> $headers
     *
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers)
    {
        $result = array();

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $line = trim((string) $value);
                if ($line !== '') {
                    $result[] = $line;
                }
                continue;
            }

            $result[] = (string) $key . ': ' . (string) $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<int, string> $headers
     *
     * @return string|null
     */
    private function resolveRequestBody(array $options, array &$headers)
    {
        if (isset($options['json'])) {
            $headers[] = 'Content-Type: application/json';
            $encoded = json_encode($options['json']);

            return $encoded === false ? '{}' : (string) $encoded;
        }

        if (isset($options['form_params']) && is_array($options['form_params'])) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';

            return http_build_query($options['form_params']);
        }

        if (isset($options['body'])) {
            return (string) $options['body'];
        }

        return null;
    }
}

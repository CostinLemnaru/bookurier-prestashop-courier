<?php
/**
 * SameDay API client.
 */

namespace Bookurier\Client\Sameday;

use Bookurier\Client\AbstractApiClient;
use Bookurier\DTO\Sameday\AuthTokenDto;
use Bookurier\DTO\Sameday\CreateAwbRequestDto;
use Bookurier\DTO\Sameday\CreateAwbResponseDto;
use Bookurier\DTO\Sameday\LockerDto;
use Bookurier\Exception\ApiException;
use Bookurier\Logging\LoggerInterface;

class SamedayClient extends AbstractApiClient implements SamedayClientInterface
{
    /**
     * @var string
     */
    const ENV_DEMO = 'demo';

    /**
     * @var string
     */
    const ENV_PROD = 'prod';

    /**
     * @var string
     */
    const BASE_URL_DEMO = 'https://sameday-api.demo.zitec.com';

    /**
     * @var string
     */
    const BASE_URL_PROD = 'https://api.sameday.ro';

    /**
     * @var string
     */
    const ENDPOINT_AUTH = '/api/authenticate';

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $tokenExpireAt;

    /**
     * @param string $username
     * @param string $password
     * @param string $environment
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        $username = '',
        $password = '',
        $environment = self::ENV_PROD,
        ?LoggerInterface $logger = null
    ) {
        $this->username = (string) $username;
        $this->password = (string) $password;
        $this->environment = self::ENV_PROD;
        $this->setEnvironment($environment);
        $this->token = '';
        $this->tokenExpireAt = '';
        $this->initializeHttpClient($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function setCredentials(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
        $this->token = '';
        $this->tokenExpireAt = '';
    }

    /**
     * {@inheritdoc}
     */
    public function setEnvironment(string $environment): void
    {
        $environment = strtolower($environment);
        $this->environment = $environment === self::ENV_DEMO ? self::ENV_DEMO : self::ENV_PROD;
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return $this->username !== '' && $this->password !== '';
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function authenticate(bool $rememberMe = true): AuthTokenDto
    {
        if (!$this->isConfigured()) {
            throw new ApiException('SameDay API credentials are not configured.');
        }

        $response = $this->requestOrFail('SameDay', 'POST', $this->buildUrl(self::ENDPOINT_AUTH), array(
            'headers' => array(
                'X-AUTH-USERNAME' => $this->username,
                'X-AUTH-PASSWORD' => $this->password,
            ),
            'form_params' => array('remember_me' => $rememberMe ? 1 : 0),
        ));

        $data = $this->decodeJsonOrFail('SameDay', $response, self::ENDPOINT_AUTH);

        if (empty($data['token'])) {
            $message = $this->extractApiErrorMessage($data);
            if ($message === '') {
                $message = 'SameDay authentication did not return a token.';
            }

            throw new ApiException($message);
        }

        $this->token = (string) $data['token'];
        $this->tokenExpireAt = isset($data['expire_at']) ? (string) $data['expire_at'] : '';

        $this->logger->info('SameDay authentication successful.', array(
            'endpoint' => self::ENDPOINT_AUTH,
            'expire_at' => $this->tokenExpireAt,
        ));

        return new AuthTokenDto($this->token, $this->tokenExpireAt);
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken(): bool
    {
        if ($this->token === '') {
            return false;
        }

        if ($this->tokenExpireAt === '') {
            return true;
        }

        $expiry = strtotime($this->tokenExpireAt);
        if ($expiry === false) {
            return true;
        }

        return $expiry > (time() + 300);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function getLockers(int $page = 1, int $perPage = 250): array
    {
        $response = $this->requestWithToken('GET', '/api/client/lockers', array(
            'query' => $this->buildPaginationQuery($page, $perPage),
        ));

        $data = $this->decodeJsonOrFail('SameDay', $response, '/api/client/lockers');
        $items = isset($data['data']) && is_array($data['data']) ? $data['data'] : array();

        $lockers = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lockers[] = LockerDto::fromApiArray($item);
        }

        return $lockers;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function getPickupPoints(int $page = 1, int $perPage = 50): array
    {
        $response = $this->requestWithToken('GET', '/api/client/pickup-points', array(
            'query' => $this->buildPaginationQuery($page, $perPage),
        ));

        $data = $this->decodeJsonOrFail('SameDay', $response, '/api/client/pickup-points');
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return array();
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function getServices(int $page = 1, int $perPage = 50): array
    {
        $response = $this->requestWithToken('GET', '/api/client/services', array(
            'query' => $this->buildPaginationQuery($page, $perPage),
        ));

        $data = $this->decodeJsonOrFail('SameDay', $response, '/api/client/services');
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return array();
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function createAwb(CreateAwbRequestDto $request): CreateAwbResponseDto
    {
        $response = $this->requestWithToken('POST', '/api/awb', array(
            'form_params' => $request->toFormArray(),
        ));

        $data = $this->decodeJsonOrFail('SameDay', $response, '/api/awb');
        $dto = CreateAwbResponseDto::fromApiResponse($data);

        if (!$dto->isSuccess()) {
            $this->logger->warning('SameDay AWB creation failed.', array('message' => $dto->getMessage()));
        } else {
            $this->logger->info('SameDay AWB created.', array('awbNumber' => $dto->getAwbNumber()));
        }

        return $dto;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function getAwbStatus(string $awbNumber): array
    {
        $awbNumber = trim($awbNumber);
        if ($awbNumber === '') {
            throw new ApiException('SameDay AWB number is required for status.');
        }

        $endpoint = '/api/client/awb/' . rawurlencode($awbNumber) . '/status';
        $response = $this->requestWithToken('GET', $endpoint);

        return $this->decodeJsonOrFail('SameDay', $response, $endpoint);
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function downloadAwbPdf(string $awbNumber): string
    {
        $awbNumber = trim($awbNumber);
        if ($awbNumber === '') {
            throw new ApiException('SameDay AWB number is required for PDF download.');
        }

        $response = $this->requestWithToken('GET', '/api/awb/download/' . rawurlencode($awbNumber));
        $statusCode = (int) $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new ApiException('SameDay AWB PDF download failed with HTTP ' . $statusCode . '.');
        }

        $body = (string) $response->getBody();
        if (strpos(trim($body), '{') === 0) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $message = $this->extractApiErrorMessage($decoded);
                if ($message === '') {
                    $message = 'SameDay AWB PDF download returned an API error.';
                }

                throw new ApiException($message);
            }
        }

        return $body;
    }

    /**
     * @return string
     *
     * @throws ApiException
     */
    private function ensureToken(): string
    {
        if ($this->hasToken()) {
            return $this->token;
        }

        $tokenDto = $this->authenticate(true);

        return $tokenDto->getToken();
    }

    /**
     * @param string $endpoint
     *
     * @return string
     */
    private function buildUrl($endpoint): string
    {
        $baseUrl = $this->environment === self::ENV_PROD ? self::BASE_URL_PROD : self::BASE_URL_DEMO;

        return rtrim($baseUrl, '/') . '/' . ltrim((string) $endpoint, '/');
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array<string, mixed> $options
     *
     * @return mixed
     *
     * @throws ApiException
     */
    private function requestWithToken($method, $endpoint, array $options = array())
    {
        $token = $this->ensureToken();
        $headers = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : array();
        $headers['X-AUTH-TOKEN'] = $token;
        $options['headers'] = $headers;

        return $this->requestOrFail('SameDay', $method, $this->buildUrl($endpoint), $options);
    }

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return array<string, int>
     */
    private function buildPaginationQuery($page, $perPage)
    {
        return array(
            'page' => (int) $page,
            // SameDay API expects countPerPage; keep perPage for compatibility.
            'countPerPage' => (int) $perPage,
            'perPage' => (int) $perPage,
        );
    }
}

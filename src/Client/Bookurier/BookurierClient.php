<?php
/**
 * Bookurier API client.
 */

namespace Bookurier\Client\Bookurier;

use Bookurier\Client\AbstractApiClient;
use Bookurier\DTO\Bookurier\CreateAwbRequestDto;
use Bookurier\DTO\Bookurier\CreateAwbResponseDto;
use Bookurier\Exception\ApiException;
use Bookurier\Logging\LoggerInterface;
use GuzzleHttp\ClientInterface;

class BookurierClient extends AbstractApiClient implements BookurierClientInterface
{
    /**
     * @var string
     */
    const BASE_URL = 'https://portal.bookurier.ro/api/';

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
    private $baseUrl;

    /**
     * @param string $username
     * @param string $password
     * @param ClientInterface|null $httpClient
     * @param LoggerInterface|null $logger
     * @param string $baseUrl
     */
    public function __construct(
        $username = '',
        $password = '',
        ClientInterface $httpClient = null,
        LoggerInterface $logger = null,
        $baseUrl = self::BASE_URL
    ) {
        $this->username = (string) $username;
        $this->password = (string) $password;
        $this->baseUrl = rtrim((string) $baseUrl, '/') . '/';
        $this->initializeHttpClient($httpClient, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function setCredentials($username, $password)
    {
        $this->username = (string) $username;
        $this->password = (string) $password;
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured()
    {
        return $this->username !== '' && $this->password !== '';
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function createAwb(CreateAwbRequestDto $request)
    {
        $this->assertConfigured();

        $payload = array(
            'user' => $this->username,
            'pwd' => $this->password,
            'data' => array($request->toApiArray()),
        );

        $this->logger->debug('Bookurier create AWB request.', array('endpoint' => 'add_cmds.php'));

        $response = $this->requestOrFail('Bookurier', 'POST', $this->buildUrl('add_cmds.php'), array(
            'json' => $payload,
        ));

        $data = $this->decodeJsonOrFail('Bookurier', $response, 'add_cmds.php');
        $dto = CreateAwbResponseDto::fromApiResponse($data);

        if (!$dto->isSuccess()) {
            $this->logger->warning('Bookurier create AWB failed.', array('message' => $dto->getMessage()));
        } else {
            $this->logger->info('Bookurier AWB created.', array('awb_codes' => $dto->getAwbCodes()));
        }

        return $dto;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function requestPickup(int $pickupPoint): array
    {
        $this->assertConfigured();
        if ($pickupPoint <= 0) {
            throw new ApiException('Pickup point must be a positive integer.');
        }

        $response = $this->requestOrFail('Bookurier', 'POST', $this->buildUrl('request_pickup.php'), array(
            'form_params' => array(
                'user' => $this->username,
                'pwd' => $this->password,
                'pickup' => (string) $pickupPoint,
            ),
        ));

        return $this->decodeJsonOrFail('Bookurier', $response, 'request_pickup.php');
    }

    /**
     * {@inheritdoc}
     *
     * @throws ApiException
     */
    public function printAwbs(array $awbCodes, $format = 'pdf', $mode = 'm', $page = 0)
    {
        $this->assertConfigured();

        $response = $this->requestOrFail('Bookurier', 'POST', $this->buildUrl('print_awbs.php'), array(
            'json' => array(
                'user' => $this->username,
                'pwd' => $this->password,
                'format' => (string) $format,
                'mode' => (string) $mode,
                'page' => (int) $page,
                'data' => $awbCodes,
            ),
        ));

        $statusCode = (int) $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new ApiException('Bookurier print AWB failed with HTTP ' . $statusCode . '.');
        }

        $body = (string) $response->getBody();
        if (strpos(trim($body), '{') === 0) {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['status']) && strtolower((string) $decoded['status']) === 'error') {
                throw new ApiException(
                    isset($decoded['message']) ? (string) $decoded['message'] : 'Bookurier print AWB returned an API error.'
                );
            }
        }

        return $body;
    }

    /**
     * @return void
     *
     * @throws ApiException
     */
    private function assertConfigured()
    {
        if (!$this->isConfigured()) {
            throw new ApiException('Bookurier API credentials are not configured.');
        }
    }

    /**
     * @param string $endpoint
     *
     * @return string
     */
    private function buildUrl($endpoint)
    {
        return $this->baseUrl . ltrim((string) $endpoint, '/');
    }
}

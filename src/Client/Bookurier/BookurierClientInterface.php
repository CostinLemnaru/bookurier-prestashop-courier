<?php
/**
 * Contract for Bookurier HTTP client.
 */

namespace Bookurier\Client\Bookurier;

use Bookurier\DTO\Bookurier\CreateAwbRequestDto;
use Bookurier\DTO\Bookurier\CreateAwbResponseDto;

interface BookurierClientInterface
{
    /**
     * @param string $username
     * @param string $password
     *
     * @return void
     */
    public function setCredentials($username, $password);

    /**
     * @return bool
     */
    public function isConfigured();

    /**
     * @param CreateAwbRequestDto $request
     *
     * @return CreateAwbResponseDto
     */
    public function createAwb(CreateAwbRequestDto $request);

    /**
     * @param int $pickupPoint Bookurier pickup point ID
     *
     * @return array<string, mixed>
     */
    public function requestPickup(int $pickupPoint): array;

    /**
     * @param array<int, string> $awbCodes
     * @param string $format
     * @param string $mode
     * @param int $page
     *
     * @return string
     */
    public function printAwbs(array $awbCodes, $format = 'pdf', $mode = 'm', $page = 0);
}

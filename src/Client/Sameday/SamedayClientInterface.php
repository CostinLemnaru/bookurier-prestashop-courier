<?php
/**
 * Contract for SameDay HTTP client.
 */

namespace Bookurier\Client\Sameday;

use Bookurier\DTO\Sameday\AuthTokenDto;
use Bookurier\DTO\Sameday\CreateAwbRequestDto;
use Bookurier\DTO\Sameday\CreateAwbResponseDto;

interface SamedayClientInterface
{
    /**
     * @param string $username
     * @param string $password
     *
     * @return void
     */
    public function setCredentials(string $username, string $password): void;

    /**
     * @param string $environment
     *
     * @return void
     */
    public function setEnvironment(string $environment): void;

    /**
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * @param bool $rememberMe
     *
     * @return AuthTokenDto
     */
    public function authenticate(bool $rememberMe = true): AuthTokenDto;

    /**
     * @return bool
     */
    public function hasToken(): bool;

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return array<int, \Bookurier\DTO\Sameday\LockerDto>
     */
    public function getLockers(int $page = 1, int $perPage = 250): array;

    /**
     * @param int $page
     * @param int $perPage
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPickupPoints(int $page = 1, int $perPage = 50): array;

    /**
     * @param CreateAwbRequestDto $request
     *
     * @return CreateAwbResponseDto
     */
    public function createAwb(CreateAwbRequestDto $request): CreateAwbResponseDto;

    /**
     * Read current AWB status payload.
     *
     * @param string $awbNumber
     *
     * @return array<string, mixed>
     */
    public function getAwbStatus(string $awbNumber): array;

    /**
     * Download AWB label PDF.
     *
     * @param string $awbNumber
     *
     * @return string
     */
    public function downloadAwbPdf(string $awbNumber): string;
}

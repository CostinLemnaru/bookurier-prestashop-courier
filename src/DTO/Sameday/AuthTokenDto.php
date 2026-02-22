<?php
/**
 * DTO for SameDay authentication token payload.
 */

namespace Bookurier\DTO\Sameday;

class AuthTokenDto
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $expireAt;

    /**
     * @param string $token
     * @param string $expireAt
     */
    public function __construct($token, $expireAt)
    {
        $this->token = (string) $token;
        $this->expireAt = (string) $expireAt;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getExpireAt()
    {
        return $this->expireAt;
    }
}


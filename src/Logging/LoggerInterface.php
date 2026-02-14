<?php
/**
 * Minimal logger abstraction for module internals.
 */

namespace Bookurier\Logging;

interface LoggerInterface
{
    /**
     * @param string $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function debug($message, array $context = array());

    /**
     * @param string $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function info($message, array $context = array());

    /**
     * @param string $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function warning($message, array $context = array());

    /**
     * @param string $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function error($message, array $context = array());
}


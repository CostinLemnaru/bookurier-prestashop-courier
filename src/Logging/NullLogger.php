<?php
/**
 * No-op logger implementation.
 */

namespace Bookurier\Logging;

class NullLogger implements LoggerInterface
{
    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = array())
    {
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = array())
    {
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = array())
    {
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = array())
    {
    }
}


<?php
/**
 * Logger factory to decouple module internals from implementation details.
 */

namespace Bookurier\Logging;

class LoggerFactory
{
    /**
     * @param string $channel
     * @param string $minimumLevel
     *
     * @return LoggerInterface
     */
    public static function create($channel = 'bookurier', $minimumLevel = 'info')
    {
        if (class_exists('\PrestaShopLogger')) {
            return new PrestaShopLoggerAdapter($channel, $minimumLevel);
        }

        return new NullLogger();
    }
}


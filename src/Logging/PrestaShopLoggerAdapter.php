<?php
/**
 * Logger adapter for PrestaShop BO logs.
 */

namespace Bookurier\Logging;

class PrestaShopLoggerAdapter implements LoggerInterface
{
    /**
     * @var string
     */
    const LEVEL_DEBUG = 'debug';

    /**
     * @var string
     */
    const LEVEL_INFO = 'info';

    /**
     * @var string
     */
    const LEVEL_WARNING = 'warning';

    /**
     * @var string
     */
    const LEVEL_ERROR = 'error';

    /**
     * @var array<string, int>
     */
    private static $weights = array(
        self::LEVEL_DEBUG => 100,
        self::LEVEL_INFO => 200,
        self::LEVEL_WARNING => 300,
        self::LEVEL_ERROR => 400,
    );

    /**
     * @var string
     */
    private $channel;

    /**
     * @var string
     */
    private $minimumLevel;

    /**
     * @param string $channel
     * @param string $minimumLevel
     */
    public function __construct($channel = 'bookurier', $minimumLevel = self::LEVEL_INFO)
    {
        $this->channel = (string) $channel;
        $this->minimumLevel = $this->normalizeLevel($minimumLevel);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = array())
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = array())
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = array())
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = array())
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     *
     * @return void
     */
    private function log($level, $message, array $context = array())
    {
        $level = $this->normalizeLevel($level);
        if (!$this->shouldLog($level)) {
            return;
        }

        $formatted = '[' . strtoupper($level) . '] [' . $this->channel . '] ' . (string) $message;
        if (!empty($context)) {
            $encoded = json_encode($context);
            if ($encoded !== false) {
                $formatted .= ' | context=' . $encoded;
            }
        }

        if (class_exists('\PrestaShopLogger')) {
            \PrestaShopLogger::addLog(
                $formatted,
                $this->toPrestaShopSeverity($level),
                null,
                $this->channel
            );

            return;
        }

        error_log($formatted);
    }

    /**
     * @param string $level
     *
     * @return int
     */
    private function toPrestaShopSeverity($level)
    {
        if ($level === self::LEVEL_ERROR) {
            return 3;
        }
        if ($level === self::LEVEL_WARNING) {
            return 2;
        }

        return 1;
    }

    /**
     * @param string $level
     *
     * @return bool
     */
    private function shouldLog($level)
    {
        return self::$weights[$level] >= self::$weights[$this->minimumLevel];
    }

    /**
     * @param string $level
     *
     * @return string
     */
    private function normalizeLevel($level)
    {
        $level = strtolower((string) $level);
        if (!isset(self::$weights[$level])) {
            return self::LEVEL_INFO;
        }

        return $level;
    }
}


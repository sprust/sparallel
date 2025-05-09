<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Psr\Log\LoggerInterface;
use Stringable;

class TestLogger implements LoggerInterface
{
    private static ?string $logFilePath = null;

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $time = date('Y-m-d H:i:s:u');

        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? null;

        if ($caller) {
            $caller = $caller['class'] . ':' . $caller['function'];
        }

        $contextText = count($context)
            ? ("\n" . json_encode($context, JSON_PRETTY_PRINT))
            : '';

        file_put_contents(
            filename: self::getLogFilePath(),
            data: sprintf(
                "%s\n%d%s\n%s%s\n",
                $time,
                getmypid(),
                $caller ? " $caller" : '',
                $message,
                $contextText
            ),
            flags: FILE_APPEND
        );
    }

    public static function flush(): void
    {
        $logFilePath = self::getLogFilePath();

        if (file_exists($logFilePath)) {
            file_put_contents($logFilePath, '');
        }
    }

    protected static function getLogFilePath(): string
    {
        if (is_null(self::$logFilePath)) {
            self::$logFilePath = __DIR__ . '/../../../tests/storage/logs/test.log';
        }

        return self::$logFilePath;
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Tests;

use DateTimeImmutable;
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
        $time = (new DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $contextText = count($context)
            ? ("\t" . json_encode($context, JSON_PRETTY_PRINT))
            : '';

        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? '';

        if ($caller) {
            $caller = "\t--------->\t" . $caller['class'] . '::' . $caller['function'];
        }

        file_put_contents(
            filename: self::getLogFilePath(),
            data: sprintf(
                "%s %s pid: %d %s%s%s\n",
                $time,
                strtoupper($level),
                getmypid(),
                $message,
                $contextText,
                $caller,
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
            self::$logFilePath = __DIR__ . '/../storage/logs/test.log';
        }

        return self::$logFilePath;
    }
}

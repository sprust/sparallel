<?php

declare(strict_types=1);

namespace SParallel\Tests;

class TestCounter
{
    public static bool $logTrace = false;

    public static string $filePath = __DIR__ . '/counter';
    private static string $traceLogFilePath = __DIR__ . '/counter-trace-log';

    public static function reset(): void
    {
        if (file_exists(self::$filePath)) {
            unlink(self::$filePath);
        }

        if (file_exists(self::$traceLogFilePath)) {
            unlink(self::$traceLogFilePath);
        }

        self::logCaller(__FUNCTION__);
    }

    public static function increment(): void
    {
        self::logCaller(__FUNCTION__);

        file_put_contents(self::$filePath, '0', FILE_APPEND);
    }

    public static function getCount(): int
    {
        self::logCaller(__FUNCTION__);

        if (!file_exists(self::$filePath)) {
            return 0;
        }

        $content = file_get_contents(self::$filePath);

        if ($content === false) {
            return 0;
        } else {
            return strlen($content);
        }
    }

    private static function isFileExists(): bool
    {
        return file_exists(self::$filePath);
    }

    private static function logCaller(string $method): void
    {
        if (!self::$logTrace) {
            return;
        }

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? null;

        if (!$backtrace) {
            return;
        }

        $class    = $backtrace['class'] ?? null;
        $function = $backtrace['function'] ?? null;
        $line     = $backtrace['line'] ?? null;

        $message = "counter::$method - $class::$function($line)" . PHP_EOL;

        file_put_contents(self::$traceLogFilePath, $message, FILE_APPEND);
    }
}

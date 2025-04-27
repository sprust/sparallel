<?php

declare(strict_types=1);

namespace SParallel\Tests;

class TestCounter
{
    public static string $filePath = __DIR__ . '/../storage/counter';

    public static function reset(): void
    {
        if (file_exists(self::$filePath)) {
            unlink(self::$filePath);
        }
    }

    public static function increment(): void
    {
        file_put_contents(self::$filePath, '0', FILE_APPEND);
    }

    public static function getCount(): int
    {
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
}

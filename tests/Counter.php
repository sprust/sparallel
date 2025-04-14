<?php

declare(strict_types=1);

namespace SParallel\Tests;

class Counter
{
    private static string $filePath = __DIR__ . '/counter';

    public static function reset(): void
    {
        if (self::isFileExists()) {
            unlink(self::$filePath);
        }
    }

    public static function increment(): void
    {
        $count = self::getCount();

        file_put_contents(self::$filePath, ++$count);
    }

    public static function getCount(): int
    {
        if (!file_exists(self::$filePath)) {
            return 0;
        }

        $count = file_get_contents(self::$filePath);

        if ($count === false || !is_numeric($count)) {
            $count = 0;
        } else {
            $count = (int) $count;
        }

        return $count;
    }

    private static function isFileExists(): bool
    {
        return file_exists(self::$filePath);
    }
}

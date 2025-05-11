<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

readonly class TestEventsRepository
{
    protected string $dirPath;

    public function __construct()
    {
        $this->dirPath = __DIR__ . '/../storage/events';
    }

    public function flush(): void
    {
        foreach ($this->getProcessPaths() as $filePath) {
            @unlink($filePath);
        }
    }

    public function add(string $eventName): void
    {
        file_put_contents("$this->dirPath/$eventName", '0', FILE_APPEND);
    }

    public function getEventsCount(string $eventName): int
    {
        $eventPath = "$this->dirPath/$eventName";

        if (!file_exists($eventPath)) {
            return 0;
        }

        return strlen(file_get_contents($eventPath));
    }

    /**
     * @return array<string>
     */
    protected function getProcessPaths(): array
    {
        $result = [];

        foreach (scandir($this->dirPath) as $fileName) {
            if (str_starts_with($fileName, '.')) {
                continue;
            }

            $result[] = "$this->dirPath/$fileName";
        }

        return $result;
    }
}

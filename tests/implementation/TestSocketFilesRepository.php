<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use SParallel\Services\Socket\SocketService;

readonly class TestSocketFilesRepository
{
    protected string $dirPath;

    public function __construct()
    {
        $this->dirPath = __DIR__ . '/../storage/sockets';
    }

    public function flush(): void
    {
        foreach ($this->getSocketPaths() as $filePath) {
            @unlink($filePath);
        }
    }

    public function getCount(): int
    {
        return count($this->getSocketPaths());
    }

    /**
     * @return array<string>
     */
    protected function getSocketPaths(): array
    {
        $result = [];

        foreach (scandir($this->dirPath) as $fileName) {
            if (!str_starts_with($fileName, SocketService::SOCKET_PATH_PREFIX)) {
                continue;
            }

            $result[] = "$this->dirPath/$fileName";
        }

        return $result;
    }
}

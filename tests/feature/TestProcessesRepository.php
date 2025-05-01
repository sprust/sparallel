<?php

declare(strict_types=1);

namespace SParallel\Tests;

readonly class TestProcessesRepository
{
    protected string $dirPath;

    public function __construct()
    {
        $this->dirPath = __DIR__ . '/../storage/processes';
    }

    public function flush(): void
    {
        foreach ($this->getProcessPaths() as $filePath) {
            @unlink($filePath);
        }
    }

    public function add(int $pid): void
    {
        file_put_contents("$this->dirPath/$pid", '0', FILE_APPEND);
    }

    public function deleteByPid(int $pid): void
    {
        @unlink("$this->dirPath/$pid");
    }

    public function getActiveCount(): int
    {
        $activeProcessesCount = 0;

        foreach ($this->getProcessPaths() as $filePath) {
            $pid = (int) basename($filePath);

            if (posix_kill($pid, SIG_DFL)) {
                ++$activeProcessesCount;
            }
        }

        return $activeProcessesCount;
    }

    /**
     * @return array<string>
     */
    protected function getProcessPaths(): array
    {
        $result = [];

        foreach (scandir($this->dirPath) as $fileName) {
            if (in_array($fileName, ['.', '..'])) {
                continue;
            }

            if (!is_numeric($fileName)) {
                continue;
            }

            $result[] = "$this->dirPath/$fileName";
        }

        return $result;
    }
}

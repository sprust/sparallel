<?php

declare(strict_types=1);

namespace SParallel\Server;

use RuntimeException;

readonly class ServerBinLoader
{
    private string $version;

    public function __construct(private string $path)
    {
        $this->version = 'latest';
    }

    public function fileExists(): bool
    {
        return file_exists($this->path);
    }

    public function load(): void
    {
        $url = $this->makeUrl();

        $tmpFilePath = $this->path . '.tmp';

        $context = stream_context_create(
            options: [
                'http' => [
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ],
            ],
            params: [
                'notification' => function (
                    int $notificationCode,
                    int $severity,
                    ?string $message,
                    int $messageCode,
                    int $bytesTransferred,
                    ?int $bytesMax
                ) {
                    if ($notificationCode == STREAM_NOTIFY_PROGRESS && $bytesMax > 0) {
                        $progress = round($bytesTransferred * 100 / $bytesMax, 4);

                        echo sprintf(
                            "\nDownloading server binary: %d%% (%d/%d bytes)",
                            $progress,
                            $bytesTransferred,
                            $bytesMax
                        );
                    }
                },
            ]
        );

        $content = file_get_contents(
            filename: $url,
            context: $context
        );

        echo "\n";

        if ($content === false) {
            throw new RuntimeException(
                "Can't download file from [$url]"
            );
        }

        file_put_contents($tmpFilePath, $content);

        rename($tmpFilePath, $this->path);

        chmod($this->path, 0755);

        echo "Server binary saved to [$this->path]\n";
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    private function makeUrl(): string
    {
        return "https://github.com/sprust/sparallel-server/releases/download/$this->version/sparallel_server";
    }
}

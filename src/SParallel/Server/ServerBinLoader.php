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

        $curl = curl_init();

        $timeout = 15;

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);

        $content = curl_exec($curl);

        curl_close($curl);

        if ($content === false) {
            throw new RuntimeException(
                "Can't download file from [$url]"
            );
        }

        $tmpFilePath = $this->path . '.tmp';

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

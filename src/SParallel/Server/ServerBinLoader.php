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

        $fp = fopen($tmpFilePath, 'wb');

        $curl = curl_init($url);

        $timeout = 60;

        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);

        $success = curl_exec($curl);

        if (!$success) {
            throw new RuntimeException(
                "Can't download file from [$url]: " . curl_error($curl)
            );
        }

        curl_close($curl);
        fclose($fp);

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

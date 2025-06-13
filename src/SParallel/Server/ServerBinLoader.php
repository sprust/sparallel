<?php

declare(strict_types=1);

namespace SParallel\Server;

use RuntimeException;

class ServerBinLoader
{
    private readonly string $version;

    private float $previousProgress = 0;

    public function __construct(private readonly string $path)
    {
        $this->version = 'latest';
    }

    public function fileExists(): bool
    {
        return file_exists($this->path);
    }

    public function load(): void
    {
        $this->previousProgress = 0;

        $url = $this->makeUrl();

        $tmpFilePath = $this->path . '.tmp';

        $fp = fopen($tmpFilePath, 'wb');

        $ch = curl_init($url);

        $timeout = 60;

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'progressCallback']);

        $success = curl_exec($ch);

        if (!$success) {
            throw new RuntimeException(
                "Can't download file from [$url]: " . curl_error($ch)
            );
        }

        curl_close($ch);
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

    private function progressCallback($resource, $downloadSize, $downloadedSize, $uploadSize, $uploadedSize): void
    {
        if ($downloadSize != 0) {
            $progress = round($downloadedSize * 100 / $downloadSize);

            if ($progress > $this->previousProgress) {
                $this->previousProgress = $progress;

                echo "Downloading server binary... $progress%\n";
            }
        }

        sleep(1);
    }
}

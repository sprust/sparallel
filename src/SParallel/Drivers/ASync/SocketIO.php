<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use RuntimeException;
use Socket;

class SocketIO
{
    protected int $timeoutSeconds;
    protected int $timeoutMicroseconds;

    public function __construct(
        protected int $bufferSize = 1024,
        protected float $timeout = 0.0001,
    ) {
        $this->timeoutSeconds      = (int) floor($this->timeout);
        $this->timeoutMicroseconds = (int) (($this->timeout * 1_000_000) - ($this->timeoutSeconds * 1_000_000));
    }

    public function makeSocketPath(): string
    {
        $socketPath = '/tmp/sparallel_socket_' . uniqid((string) microtime(true));

        if (file_exists($socketPath)) {
            unlink($socketPath);
        }

        return $socketPath;
    }

    public function createServer(string $socketPath): Socket
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        socket_bind($socket, $socketPath);
        socket_listen($socket);
        socket_set_nonblock($socket);

        return $socket;
    }

    public function createClient(string $socketPath): Socket
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (!socket_connect($socket, $socketPath)) {
            throw new RuntimeException(
                'Could not connect to socket: ' . socket_strerror(socket_last_error($socket))
            );
        }

        return $socket;
    }

    public function readSocket(Socket $socket): string
    {
        socket_set_nonblock($socket);

        $lengthHeader = '';

        while (strlen($lengthHeader) < 4) {
            $chunk = socket_read($socket, 4 - strlen($lengthHeader));

            if ($chunk === false || $chunk === '') {
                usleep(1000);

                continue;
            }

            $lengthHeader .= $chunk;
        }

        $data       = '';
        $dataLength = unpack('N', $lengthHeader)[1];

        while (strlen($data) < $dataLength) {
            $chunk = socket_read($socket, min(8192, $dataLength - strlen($data)));

            if ($chunk === false || $chunk === '') {
                usleep(1000);

                continue;
            }

            $data .= $chunk;
        }

        return $data;
    }

    public function writeToSocket(Socket $socket, string $data): void
    {
        socket_set_nonblock($socket);

        $sentBytes  = 0;
        $dataLength = strlen($data);

        $lengthHeader = pack('N', $dataLength);

        while ($sentBytes < 4) {
            $bytes = socket_write($socket, substr($lengthHeader, $sentBytes), 4 - $sentBytes);

            if ($bytes === false) {
                usleep(1000);

                continue;
            }

            $sentBytes += $bytes;
        }

        $sentBytes = 0;
        $chunkSize = 8192;

        while ($sentBytes < $dataLength) {
            $chunk = substr($data, $sentBytes, $chunkSize);
            $bytes = socket_write($socket, $chunk, strlen($chunk));

            if ($bytes === false) {
                usleep(1000);

                continue;
            }

            $sentBytes += $bytes;
        }
    }
}

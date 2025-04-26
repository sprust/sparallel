<?php

declare(strict_types=1);

namespace SParallel\Services\Socket;

use Socket;
use SParallel\Exceptions\CancelerException;
use SParallel\Exceptions\CouldNotConnectToSocketException;
use SParallel\Exceptions\CouldNotCreateSocketServerException;
use SParallel\Objects\SocketClient;
use SParallel\Objects\SocketServer;
use SParallel\Services\Canceler;

// TODO: use SOMAXCONN const for limiting connections
readonly class SocketService
{
    protected int $timeoutSeconds;
    protected int $timeoutMicroseconds;

    public function __construct(
        protected string $socketPathDirectory = '/tmp',
        protected int $bufferSize = 1024,
        protected float $timeout = 0.0001,
    ) {
        $this->timeoutSeconds      = (int) floor($this->timeout);
        $this->timeoutMicroseconds = (int) (($this->timeout * 1_000_000) - ($this->timeoutSeconds * 1_000_000));
    }

    public function makeSocketPath(): string
    {
        $socketPath = sprintf(
            rtrim($this->socketPathDirectory, '/') . '/sparallel_socket_%d_%f_%s',
            getmypid(),
            microtime(true),
            uniqid(more_entropy: true)
        );

        if (file_exists($socketPath)) {
            unlink($socketPath);
        }

        return $socketPath;
    }

    public function createServer(string $socketPath): SocketServer
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if ($socket === false) {
            throw new CouldNotCreateSocketServerException($socketPath);
        }

        socket_bind($socket, $socketPath);
        socket_listen($socket, SOMAXCONN);
        socket_set_nonblock($socket);

        return new SocketServer(
            path: $socketPath,
            socket: $socket
        );
    }

    public function createClient(string $socketPath): SocketClient
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        if (!socket_connect($socket, $socketPath)) {
            throw new CouldNotConnectToSocketException($socket);
        }

        return new SocketClient(
            socket: $socket
        );
    }

    /**
     * @throws CancelerException
     */
    public function readSocket(Canceler $canceler, Socket $socket): string
    {
        socket_set_nonblock($socket);

        $lengthHeader = '';

        while (strlen($lengthHeader) < 4) {
            $chunk = socket_read($socket, 4 - strlen($lengthHeader));

            if ($chunk === false || $chunk === '') {
                $canceler->check();

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
                $canceler->check();

                usleep(1000);

                continue;
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @throws CancelerException
     */
    public function writeToSocket(Canceler $canceler, Socket $socket, string $data): void
    {
        socket_set_nonblock($socket);

        $sentBytes  = 0;
        $dataLength = strlen($data);

        $lengthHeader = pack('N', $dataLength);

        while ($sentBytes < 4) {
            $bytes = socket_write($socket, substr($lengthHeader, $sentBytes), 4 - $sentBytes);

            if ($bytes === false) {
                $canceler->check();

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
                $canceler->check();

                usleep(1000);

                continue;
            }

            $sentBytes += $bytes;
        }
    }
}

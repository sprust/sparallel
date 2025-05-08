<?php

declare(strict_types=1);

namespace SParallel\Services\Socket;

use Socket;
use SParallel\Entities\SocketClient;
use SParallel\Entities\SocketServer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\CouldNotConnectToSocketException;
use SParallel\Exceptions\CouldNotCreateSocketServerException;
use SParallel\Services\Context;

readonly class SocketService
{
    public const SOCKET_PATH_PREFIX = 'sparallel_socket';

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
            rtrim($this->socketPathDirectory, '/') . '/%s_%d_%f_%s',
            self::SOCKET_PATH_PREFIX,
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

        @socket_bind($socket, $socketPath);
        @socket_listen($socket, SOMAXCONN);
        @socket_set_nonblock($socket);

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

    public function accept(Socket $socket): Socket|false
    {
        return @socket_accept($socket);
    }

    /**
     * @throws ContextCheckerException
     */
    public function readSocket(Context $context, Socket $socket): string
    {
        socket_set_nonblock($socket);

        $lengthHeader = '';

        while (strlen($lengthHeader) < 4) {
            $chunk = socket_read($socket, 4 - strlen($lengthHeader));

            if ($chunk === false || $chunk === '') {
                $context->check();

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
                $context->check();

                usleep(1000);

                continue;
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @throws ContextCheckerException
     */
    public function writeToSocket(Context $context, Socket $socket, string $data): void
    {
        socket_set_nonblock($socket);

        $sentBytes  = 0;
        $dataLength = strlen($data);

        $lengthHeader = pack('N', $dataLength);

        while ($sentBytes < 4) {
            $bytes = socket_write($socket, substr($lengthHeader, $sentBytes), 4 - $sentBytes);

            if ($bytes === false) {
                $context->check();

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
                $context->check();

                usleep(1000);

                continue;
            }

            $sentBytes += $bytes;
        }
    }
}

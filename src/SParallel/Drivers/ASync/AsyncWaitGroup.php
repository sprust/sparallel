<?php

declare(strict_types=1);

namespace SParallel\Drivers\ASync;

use Generator;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\ResultObject;
use SParallel\Objects\SocketServerObject;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;

class AsyncWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, SocketServerObject> $childSocketServers
     */
    public function __construct(
        protected Process $process,
        protected array $childSocketServers,
        protected Timer $timer,
        protected EventsBusInterface $eventsBus,
        protected SocketService $socketService,
        protected ResultTransport $resultTransport,
    ) {
    }

    public function get(): Generator
    {
        while (count($this->childSocketServers) > 0) {
            $callbackKeys = array_keys($this->childSocketServers);

            foreach ($callbackKeys as $callbackKey) {
                $childSocketServer = $this->childSocketServers[$callbackKey];

                $childClient = @socket_accept($childSocketServer->socket);

                if ($childClient === false) {
                    if (!$this->process->isRunning()) {
                        if ($pid = $this->process->getPid()) {
                            $this->eventsBus->processFinished(pid: $pid);
                        }

                        $this->socketService->closeSocketServer($childSocketServer);

                        unset($this->childSocketServers[$callbackKey]);

                        yield new ResultObject(
                            key: $callbackKey,
                            exception: new RuntimeException(
                                message: "Process with key [$callbackKey] is not running. Socket server is closed."
                            )
                        );
                    } else {
                        $this->timer->check();

                        usleep(1000);
                    }
                } else {
                    try {
                        $response = $this->socketService->readSocket(
                            timer: $this->timer,
                            socket: $childClient
                        );
                    } finally {
                        $this->socketService->closeSocketServer($childSocketServer);
                    }

                    unset($this->childSocketServers[$callbackKey]);

                    yield $this->resultTransport->unserialize($response);
                }
            }
        }
    }

    public function break(): void
    {
        $keys = array_keys($this->childSocketServers);

        foreach ($keys as $key) {
            $serverObject = $this->childSocketServers[$key];

            $this->socketService->closeSocketServer($serverObject);

            unset($this->childSocketServers[$key]);
        }
    }

    public function __destruct()
    {
        $this->break();
    }
}

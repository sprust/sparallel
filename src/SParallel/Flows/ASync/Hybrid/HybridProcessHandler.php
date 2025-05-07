<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use SParallel\Contracts\EventsBusInterface;
use SParallel\Entities\Timer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Flows\ASync\Fork\Forker;
use SParallel\Flows\ASync\Fork\ForkService;
use SParallel\Flows\ASync\Fork\ForkTaskManager;
use SParallel\Flows\FlowFactory;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessHandler
{
    public function __construct(
        protected ContextTransport $contextTransport,
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected Forker $forkExecutor,
        protected ForkService $forkService,
        protected FlowFactory $flowFactory,
        protected ForkTaskManager $forkTaskManager,
        protected ProcessService $processService,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function handle(): void
    {
        $pid = getmypid();

        $this->eventsBus->processCreated($pid);

        $this->processService->registerShutdownFunction(
            function () use ($pid) {
                $this->eventsBus->processFinished(pid: $pid);

                exit(0);
            }
        );

        $parentSocketPath = $_SERVER[HybridTaskManager::PARAM_PARENT_SOCKET_PATH] ?? null;

        if (!$parentSocketPath || !is_string($parentSocketPath)) {
            throw new InvalidValueException(
                'Parent socket path is not set or is not a string.'
            );
        }

        $flowSocketPath = $_SERVER[HybridTaskManager::PARAM_FLOW_SOCKET_PATH] ?? null;

        if (!$flowSocketPath || !is_string($flowSocketPath)) {
            throw new InvalidValueException(
                'Flow socket path is not set or is not a string.'
            );
        }

        $workersLimit = $_SERVER[HybridTaskManager::PARAM_WORKERS_LIMIT] ?? null;

        if (is_null($workersLimit)) {
            throw new InvalidValueException(
                'Workers limit is not set.'
            );
        }

        if (!is_numeric($workersLimit)) {
            throw new InvalidValueException(
                'Workers limit is not a number.'
            );
        }

        $workersLimit = (int) $workersLimit;

        $parentSocketClient = $this->socketService->createClient($parentSocketPath);

        $initContext = new Context();

        $response = $this->socketService->readSocket(
            context: $initContext->addChecker(new Timer(timeoutSeconds: 2)),
            socket: $parentSocketClient->socket
        );

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['ctx']);

        $callbacks = array_map(
            fn(string $serializedCallbacks) => $this->callbackTransport->unserialize($serializedCallbacks),
            $responseData['cbs']
        );

        $flow = $this->flowFactory->create(
            callbacks: $callbacks,
            context: $context,
            workersLimit: $workersLimit,
            taskManager: $this->forkTaskManager,
            socketServer: $this->socketService->createServer(
                socketPath: $flowSocketPath,
            )
        );

        foreach ($flow->get() as $taskResult) {
            $parentSocketClient = $this->socketService->createClient($parentSocketPath);

            if ($taskResult->error) {
                fwrite(STDERR, "$taskResult->taskKey: ERROR: {$taskResult->error->message}\n");
            } else {
                fwrite(STDOUT, "$taskResult->taskKey: SUCCESS: {$taskResult->error->message}\n");
            }

            $this->socketService->writeToSocket(
                context: $context,
                socket: $parentSocketClient->socket,
                data: serialize($taskResult->taskKey)
            );
        }

        $flow->break();
    }
}

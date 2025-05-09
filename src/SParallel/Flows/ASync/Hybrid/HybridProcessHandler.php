<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Hybrid;

use Psr\Log\LoggerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Entities\Timer;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\UnexpectedTaskException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Flows\ASync\Fork\ForkDriver;
use SParallel\Flows\ASync\Fork\Forker;
use SParallel\Flows\ASync\Fork\ForkService;
use SParallel\Flows\FlowFactory;
use SParallel\Objects\Message;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;

class HybridProcessHandler
{
    /**
     * @var array<int|string, int> $activeTaskPids
     */
    protected array $activeTaskPids;

    public function __construct(
        protected ContextTransport $contextTransport,
        protected EventsBusInterface $eventsBus,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected SocketService $socketService,
        protected Forker $forkExecutor,
        protected ForkService $forkService,
        protected FlowFactory $flowFactory,
        protected ForkDriver $forkDriver,
        protected ProcessService $processService,
        protected MessageTransport $messageTransport,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ContextCheckerException
     */
    public function handle(): void
    {
        $this->activeTaskPids = [];

        $myPid = getmypid();

        $this->logger->debug(
            sprintf(
                "hybrid handler started [hPid: %s]",
                $myPid
            )
        );

        $this->eventsBus->processCreated($myPid);

        $exitHandler = function () use ($myPid) {
            foreach ($this->activeTaskPids as $activeTaskPid) {
                $this->forkService->finish($activeTaskPid);
            }

            $this->eventsBus->processFinished(pid: $myPid);

            $this->logger->debug(
                sprintf(
                    "hybrid handler closing by handler [hPid: %s]",
                    $myPid
                )
            );

            exit(0);
        };

        $this->processService->registerShutdownFunction($exitHandler);
        $this->processService->registerExitSignals($exitHandler);

        $driverSocketPath = $_SERVER[HybridDriver::PARAM_DRIVER_SOCKET_PATH] ?? null;

        if (!$driverSocketPath || !is_string($driverSocketPath)) {
            throw new InvalidValueException(
                'Driver socket path is not set or is not a string.'
            );
        }

        $flowSocketPath = $_SERVER[HybridDriver::PARAM_FLOW_SOCKET_PATH] ?? null;

        if (!$flowSocketPath || !is_string($flowSocketPath)) {
            throw new InvalidValueException(
                'Flow socket path is not set or is not a string.'
            );
        }

        $client = $this->socketService->createClient($driverSocketPath);

        $initContext = new Context();

        $response = $this->socketService->readSocket(
            context: $initContext->setChecker(new Timer(timeoutSeconds: 2)),
            socket: $client->socket
        );

        $this->logger->debug(
            sprintf(
                "hybrid handler got payload from driver [hPid: %s]",
                $myPid
            )
        );

        unset($client);

        $responseData = json_decode($response, true);

        $context = $this->contextTransport->unserialize($responseData['ctx']);

        $callbacks = array_map(
            fn(string $serializedCallbacks) => $this->callbackTransport->unserialize($serializedCallbacks),
            $responseData['cbs']
        );

        $socketServer = $this->socketService->createServer(
            $this->socketService->makeSocketPath()
        );

        $client = $this->socketService->createClient($driverSocketPath);

        $this->socketService->writeToSocket(
            context: $initContext->setChecker(new Timer(timeoutSeconds: 2)),
            socket: $client->socket,
            data: $socketServer->path
        );

        $this->logger->debug(
            sprintf(
                "hybrid handler sent self socket path to driver [hPid: %s]",
                $myPid
            )
        );

        unset($client);

        while (count($callbacks) > 0 || count($this->activeTaskPids) > 0) {
            $activeTaskKeys = array_keys($this->activeTaskPids);

            foreach ($activeTaskKeys as $activeTaskKey) {
                $taskPid = $this->activeTaskPids[$activeTaskKey];

                if (!$this->forkService->isFinished($taskPid)) {
                    continue;
                }

                $this->forkService->finish($taskPid);

                unset($this->activeTaskPids[$activeTaskKey]);

                $client = $this->socketService->createClient($driverSocketPath);

                $this->socketService->writeToSocket(
                    context: $context,
                    socket: $client->socket,
                    data: $this->messageTransport->serialize(
                        new Message(
                            operation: MessageOperationTypeEnum::TaskFinished,
                            taskKey: $activeTaskKey,
                        )
                    )
                );

                $this->logger->debug(
                    sprintf(
                        "hybrid handler sent task finished signal [hPid: %d, tKey: %s, tPid: %d]",
                        $myPid,
                        $activeTaskKey,
                        $taskPid
                    )
                );

                unset($client);
            }

            $client = $this->socketService->accept(
                socket: $socketServer->socket
            );

            if ($client === false) {
                $context->check();

                usleep(100);

                continue;
            }

            $response = $this->socketService->readSocket(
                context: $context,
                socket: $client
            );

            unset($client);

            $message = $this->messageTransport->unserialize($response);

            $this->logger->debug(
                sprintf(
                    "hybrid handler got message from driver [hPid: %s, mTKey: %s, mOp: %d]",
                    $myPid,
                    $message->taskKey,
                    $message->operation->name,
                )
            );

            $taskKey = $message->taskKey;

            if ($message->operation === MessageOperationTypeEnum::TaskStart) {
                if (!array_key_exists($taskKey, $callbacks)) {
                    throw new UnexpectedTaskException(
                        unexpectedTaskKey: $taskKey
                    );
                }

                $taskPid = $this->forkExecutor->fork(
                    context: $context,
                    driverName: HybridDriver::DRIVER_NAME,
                    socketPath: $flowSocketPath,
                    taskKey: $taskKey,
                    callback: $callbacks[$taskKey],
                );

                $this->logger->debug(
                    sprintf(
                        "hybrid handler forked task [hPid: %s, mTKey: %s, mOp: %d, tPid: %d]",
                        $myPid,
                        $message->taskKey,
                        $message->operation->name,
                        $taskPid,
                    )
                );

                $this->activeTaskPids[$taskKey] = $taskPid;

                unset($callbacks[$taskKey]);
            } else {
                throw new UnexpectedTaskOperationException(
                    taskKey: $taskKey,
                    operation: $message->operation->value,
                );
            }
        }
    }
}

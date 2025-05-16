<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use LogicException;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Entities\Timer;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\InvalidValueException;
use SParallel\Exceptions\UnexpectedTaskOperationException;
use SParallel\Objects\Message;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ProcessHandler
{
    public function __construct(
        protected SocketService $socketService,
        protected MessageTransport $messageTransport,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected CallbackCallerInterface $callbackCaller,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected ProcessService $processService,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $myPid = getmypid();

        $this->logger->debug(
            sprintf(
                "process handler started [pPid: %s]",
                $myPid
            )
        );

        $this->eventsBus->processCreated(pid: $myPid);

        $stdin = fopen('php://stdin', 'r');

        while (true) {
            $lenPacked = fread($stdin, 4);

            if ($lenPacked === false) {
                usleep(100);

                continue;
            }

            if (strlen($lenPacked) !== 4) {
                throw new LogicException(
                    'Unexpected end of stream.'
                );
            }

            $len = unpack('N', $lenPacked)[1];

            $payload = '';

            while (strlen($payload) < $len) {
                $payload .= fread($stdin, $len - strlen($payload));
            }

            $this->logger->debug(
                sprintf(
                    "process handler got payload from flow [pPid: %s]\n%s",
                    $myPid,
                    $payload
                )
            );

            try {
                $result = $this->onHandle($myPid, $payload);
            } catch (Throwable $exception) {
                $this->logger->error(
                    sprintf(
                        "process handler got error at handling [pPid: %s]: %s\n%s",
                        $myPid,
                        $exception->getMessage(),
                        $exception
                    )
                );

                throw $exception;
            } finally {
                $this->logger->debug(
                    sprintf(
                        "process handler finished [pPid: %s]",
                        $myPid
                    )
                );
            }

            fflush(STDOUT);

            $serializedResult = $this->resultTransport->serialize($result);

            fwrite(STDOUT, pack('N', strlen($serializedResult)));
            fwrite(STDOUT, $serializedResult);

            fflush(STDIN);
        }

        // TODO
        //$this->eventsBus->processFinished(pid: $myPid);
    }

    /**
     * @throws Throwable
     */
    protected function onHandle(int $myPid, string $payload): TaskResult
    {
        $message = $this->messageTransport->unserialize($payload);

        $this->logger->debug(
            sprintf(
                "process handler got message from flow [op: %s, mOp: %s]",
                $message->operation->name,
                $message->taskKey,
            )
        );

        if ($message->operation === MessageOperationTypeEnum::StartTask) {
            return $this->handleStartTask($myPid, $message);
        }

        return new TaskResult(
            taskKey: $message->taskKey,
            exception: new UnexpectedTaskOperationException(
                taskKey: $message->taskKey,
                operation: $message->operation->value,
            )
        );
    }

    private function handleStartTask(int $myPid, Message $message): TaskResult
    {
        $taskKey = $message->taskKey;

        $context = $this->contextTransport->unserialize($message->serializedContext);

        $driverName = ProcessDriver::DRIVER_NAME;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $context
        );

        try {
            $callback = $this->callbackTransport->unserialize(
                $message->payload
            );

            $result = $this->callbackCaller->call(
                callback: $callback,
                context: $context
            );

            $this->logger->debug(
                sprintf(
                    "process handler sent task result to flow [pPid: %s, tKey: %s]",
                    $myPid,
                    $taskKey
                )
            );

            return new TaskResult(
                taskKey: $taskKey,
                result: $result
            );
        } catch (Throwable $exception) {
            $this->eventsBus->taskFailed(
                driverName: $driverName,
                context: $context,
                exception: $exception
            );

            $this->logger->error(
                sprintf(
                    "process handler sent error to flow [fPid: %d, tKey: %s]: %s\n%s",
                    $myPid,
                    $taskKey,
                    $exception->getMessage(),
                    $exception
                )
            );

            return new TaskResult(
                taskKey: $taskKey,
                exception: $exception
            );
        } finally {
            $this->eventsBus->taskFinished(
                driverName: $driverName,
                context: $context
            );
        }
    }
}

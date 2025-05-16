<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use Closure;
use LogicException;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Enum\MessageOperationTypeEnum;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Objects\Message;
use SParallel\Objects\TaskResult;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\ResultTransport;
use Throwable;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    protected string $command;

    /**
     * @var array<Process>
     */
    protected array $freeProcesses;

    /**
     * @var array<int|string, Process>
     */
    protected array $workingProcesses;

    public function __construct(
        protected ProcessCommandResolverInterface $processCommandResolver,
        protected ProcessService $processService,
        protected MessageTransport $messageTransport,
        protected CallbackTransport $callbackTransport,
        protected ContextTransport $contextTransport,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
        protected LoggerInterface $logger,
    ) {
        $this->command = $this->processCommandResolver->get();
    }

    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
    ): void {
        $this->freeProcesses    = [];
        $this->workingProcesses = [];

        $processesCount = min(count($callbacks), $workersLimit);

        while ($processesCount > 0) {
            --$processesCount;

            $this->addFreeProcess();
        }
    }

    public function createTask(
        Context $context,
        int|string $taskKey,
        Closure $callback
    ): TaskInterface {
        $process = array_pop($this->freeProcesses);

        if (!$process) {
            throw new LogicException('No free processes');
        }

        $message = new Message(
            MessageOperationTypeEnum::StartTask,
            taskKey: $taskKey,
            serializedContext: $this->contextTransport->serialize($context),
            payload: $this->callbackTransport->serialize($callback),
        );

        if (!$process->isRunning()) {
            $process->start();

            $this->eventsBus->processCreated(pid: $process->getPid());;
        }

        $process->write(
            $this->messageTransport->serialize($message)
        );

        $this->logger->debug(
            sprintf(
                "process driver sent task to process [pPid: %s, tKey: %s]",
                $process->getPid(),
                $taskKey,
            )
        );

        $this->workingProcesses[$taskKey] = $process;

        return new ProcessTask(
            context: $context,
            pid: $process->getPid(),
            taskKey: $taskKey,
            callback: $callback,
            process: $process,
            processService: $this->processService,
            processDriver: $this,
            logger: $this->logger,
        );
    }

    public function getResult(Context $context): TaskResult|false
    {
        $taskKeys = array_keys($this->workingProcesses);

        foreach ($taskKeys as $taskKey) {
            $context->check();

            $process = $this->workingProcesses[$taskKey];

            if ($process->isFinished()) {
                unset($this->workingProcesses[$taskKey]);

                $this->addFreeProcess();

                continue;
            }

            $output = $process->read();

            if ($output === false) {
                continue;
            }

            unset($this->workingProcesses[$taskKey]);

            $this->freeProcesses[] = $process;

            try {
                return $this->resultTransport->unserialize($output);
            } catch (Throwable $exception) {
                return new TaskResult(
                    taskKey: $taskKey,
                    exception: new LogicException(
                        message: sprintf(
                            "Process driver got error at unserializing result\n%s\n%s",
                            $exception,
                            $output
                        ),
                        previous: $exception,
                    ),
                );
            }
        }

        return false;
    }

    /**
     * @throws ContextCheckerException
     */
    public function break(Context $context): void
    {
        $this->stopProcesses($context, $this->freeProcesses);
        $this->stopProcesses($context, $this->workingProcesses);
    }

    public function isTaskActive(int|string $taskKey): bool
    {
        return isset($this->workingProcesses[$taskKey]);
    }

    private function addFreeProcess(): void
    {
        $this->freeProcesses[] = new Process($this->command);
    }

    /**
     * @param array<Process> $processes
     *
     * @throws ContextCheckerException
     */
    private function stopProcesses(Context $context, array &$processes): void
    {
        $keys = array_keys($processes);

        foreach ($keys as $key) {
            $process = $processes[$key];

            if ($process->isRunning()) {
                $pid = $process->getPid();

                $process->stop();

                $this->processService->killChildren(
                    context: $context,
                    caller: 'process driver',
                    pid: $pid
                );

                $this->logger->debug(
                    sprintf(
                        "process driver stops process [pPid: %s]",
                        $pid
                    )
                );
            }

            unset($processes[$key]);
        }
    }

    /**
     * @throws ContextCheckerException
     */
    public function __destruct()
    {
        $this->break(new Context());
    }
}

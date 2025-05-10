<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use Closure;
use Psr\Log\LoggerInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Entities\SocketServer;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const PARAM_TASK_KEY    = 'task_key';
    public const PARAM_SOCKET_PATH = 'socket_path';

    protected string $command;

    /**
     * @var array<int|string, Process>
     */
    protected array $processes;

    public function __construct(
        protected ProcessCommandResolverInterface $processCommandResolver,
        protected ProcessService $processService,
        protected LoggerInterface $logger,
    ) {
        $this->command = $this->processCommandResolver->get();
    }

    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void {
        $this->processes = [];
    }

    public function create(
        Context $context,
        SocketServer $socketServer,
        int|string $key,
        Closure $callback
    ): TaskInterface {
        $process = Process::fromShellCommandline(command: $this->command)
            ->setTimeout(null)
            ->setEnv([
                ProcessDriver::PARAM_TASK_KEY    => serialize($key),
                ProcessDriver::PARAM_SOCKET_PATH => $socketServer->path,
            ]);

        $process->start();

        $this->processes[$key] = $process;

        return new ProcessTask(
            context: $context,
            pid: $process->getPid(),
            taskKey: $key,
            callback: $callback,
            process: $process,
            processService: $this->processService,
            logger: $this->logger,
        );
    }

    public function break(Context $context): void
    {
        $keys = array_keys($this->processes);

        foreach ($keys as $key) {
            $process = $this->processes[$key];

            if ($process->isRunning()) {
                $pid = $process->getPid();

                try {
                    $process->stop();
                } catch (Throwable) {
                    //
                }

                $this->logger->debug(
                    sprintf(
                        "process driver stops process [pPid: %s]",
                        $pid
                    )
                );
            }

            unset($this->processes[$key]);
        }
    }
}

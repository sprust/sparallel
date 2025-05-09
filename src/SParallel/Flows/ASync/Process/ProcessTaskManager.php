<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use Closure;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\TaskInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Entities\SocketServer;
use SParallel\Services\Context;
use SParallel\Services\Process\ProcessService;
use Symfony\Component\Process\Process;

class ProcessTaskManager implements TaskManagerInterface
{
    public const DRIVER_NAME = 'process';

    public const PARAM_TASK_KEY = 'task_key';
    public const PARAM_SOCKET_PATH = 'socket_path';

    protected string $command;

    public function __construct(
        protected ProcessCommandResolverInterface $processCommandResolver,
        protected ProcessService $processService,
    ) {
        $this->command = $this->processCommandResolver->get();
    }

    public function init(
        Context $context,
        array $callbacks,
        int $workersLimit,
        SocketServer $socketServer
    ): void {
        //
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
                ProcessTaskManager::PARAM_TASK_KEY    => serialize($key),
                ProcessTaskManager::PARAM_SOCKET_PATH => $socketServer->path,
            ]);

        $process->start();

        return new ProcessTask(
            pid: $process->getPid(),
            taskKey: $key,
            callback: $callback,
            process: $process,
            processService: $this->processService
        );
    }
}

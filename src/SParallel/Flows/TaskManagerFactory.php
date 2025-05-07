<?php

declare(strict_types=1);

namespace SParallel\Flows;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\TaskManagerFactoryInterface;
use SParallel\Contracts\TaskManagerInterface;
use SParallel\Flows\ASync\Fork\ForkTaskManager;
use SParallel\Flows\ASync\Process\ProcessTaskManager;

class TaskManagerFactory implements TaskManagerFactoryInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected ?bool $isRunningInConsole = null,
        protected ?TaskManagerInterface $taskManager = null,
    ) {
    }

    public function forceDriver(?TaskManagerInterface $taskManager): void
    {
        $this->taskManager = $taskManager;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function detect(): TaskManagerInterface
    {
        if ($this->taskManager) {
            return $this->taskManager;
        }

        if ($this->runningInConsole()) {
            $taskManager = $this->container->get(ForkTaskManager::class);
        } else {
            $taskManager = $this->container->get(ProcessTaskManager::class);
        }

        return $this->taskManager = $taskManager;
    }

    private function runningInConsole(): bool
    {
        if (!is_null($this->isRunningInConsole)) {
            return $this->isRunningInConsole;
        }

        return $this->isRunningInConsole = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
    }
}

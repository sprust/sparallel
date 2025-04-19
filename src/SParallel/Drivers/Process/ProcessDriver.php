<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Generator;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Drivers\Timer;
use SParallel\Objects\Context;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const TASK_KEY                         = 'SPARALLEL_TASK_KEY';
    public const SERIALIZED_CLOSURE_VARIABLE_NAME = 'SPARALLEL_SERIALIZED_CLOSURE';
    public const SERIALIZED_CONTEXT_VARIABLE_NAME = 'SPARALLEL_SERIALIZED_CONTEXT';
    public const TIMER_TIMEOUT_SECONDS            = 'SPARALLEL_TIMER_TIMEOUT_SECONDS';
    public const TIMER_START_TIME                 = 'SPARALLEL_TIMER_START_TIME';

    protected string $scriptPath;

    public function __construct(
        protected ProcessConnectionInterface $connection,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ContextTransport $contextTransport,
        protected ProcessScriptPathResolverInterface $processScriptPathResolver,
        protected ?Context $context = null
    ) {
        $this->scriptPath = $this->processScriptPathResolver->get();
    }

    public function run(array &$callbacks, Timer $timer): Generator
    {
        $this->checkScriptPath();

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $serializedContext = $this->contextTransport->serialize($this->context);

        $callbackKeys = array_keys($callbacks);

        $processes = [];

        foreach ($callbackKeys as $callbackKey) {
            $callback = $callbacks[$callbackKey];

            $process = Process::fromShellCommandline(command: $command)
                ->setTimeout(null)
                ->setEnv([
                    static::TASK_KEY                         => $callbackKey,
                    static::SERIALIZED_CLOSURE_VARIABLE_NAME => $this->callbackTransport->serialize($callback),
                    static::SERIALIZED_CONTEXT_VARIABLE_NAME => $serializedContext,
                    static::TIMER_TIMEOUT_SECONDS            => $timer->timeoutSeconds,
                    static::TIMER_START_TIME                 => $timer->startTime,
                ]);

            $process->start();

            $processes[$callbackKey] = $process;

            unset($callbacks[$callbackKey]);
        }

        while (count($processes) > 0) {
            $keys = array_keys($processes);

            foreach ($keys as $key) {
                $process = $processes[$key];

                if ($process->isRunning()) {
                    continue;
                }

                $output = $this->connection->read($process);

                unset($processes[$key]);

                yield $this->resultTransport->unserialize($output);
            }
        }
    }

    private function checkScriptPath(): void
    {
        $scriptPath = explode(' ', $this->scriptPath)[0];

        if (!file_exists($scriptPath)) {
            throw new RuntimeException(
                message: sprintf(
                    'Script path [%s] does not exist.',
                    $scriptPath,
                )
            );
        }
    }
}

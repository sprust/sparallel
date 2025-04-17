<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\Context;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const DRIVER_NAME = 'process';

    public const SERIALIZED_CLOSURE_VARIABLE_NAME = 'SPARALLEL_SERIALIZED_CLOSURE';
    public const SERIALIZED_CONTEXT_VARIABLE_NAME = 'SPARALLEL_SERIALIZED_CONTEXT';

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

    public function wait(array $callbacks): WaitGroupInterface
    {
        $this->checkScriptPath();

        $processes = [];

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $serializedContext = $this->contextTransport->serialize($this->context);

        foreach ($callbacks as $key => $callback) {
            $process = Process::fromShellCommandline(command: $command)
                ->setTimeout(null)
                ->setEnv([
                    static::SERIALIZED_CLOSURE_VARIABLE_NAME => $this->callbackTransport->serialize($callback),
                    static::SERIALIZED_CONTEXT_VARIABLE_NAME => $serializedContext,
                ]);

            $process->start();

            $processes[$key] = $process;
        }

        return new ProcessWaitGroup(
            connection: $this->connection,
            resultTransport: $this->resultTransport,
            processes: $processes,
        );
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

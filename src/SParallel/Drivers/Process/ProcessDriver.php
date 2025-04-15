<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\Context;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\Serializer;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const SERIALIZED_CLOSURE_VARIABLE_NAME = 'SPARALLEL_SERIALIZED_CLOSURE';
    public const SERIALIZED_CONTEXT_VARIABLE_NAME = 'SPARALLEL_SERIALIZED_CONTEXT';

    public function __construct(
        protected string $scriptPath,
        protected ?Context $context = null
    ) {
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

        $serializedContext = ContextTransport::serialize($this->context);

        foreach ($callbacks as $key => $callback) {
            $process = Process::fromShellCommandline(command: $command)
                ->setTimeout(null)
                ->setEnv([
                    static::SERIALIZED_CLOSURE_VARIABLE_NAME => Serializer::serialize($callback),
                    static::SERIALIZED_CONTEXT_VARIABLE_NAME => $serializedContext,
                ]);

            $process->start();

            $processes[$key] = $process;
        }

        return new ProcessWaitGroup(
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

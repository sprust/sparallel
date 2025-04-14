<?php

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;

class Container implements ContainerInterface
{
    /**
     * @var array<class-string, Closure(): object>
     */
    private array $resolvers;

    public function __construct()
    {
        $this->resolvers = [
            SyncDriver::class    => static fn() => new SyncDriver(),
            ProcessDriver::class => static fn() => new ProcessDriver(
                __DIR__ . '/process-handler.php'
            ),
            ForkDriver::class    => static fn() => new ForkDriver(),
        ];
    }

    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new RuntimeException("No entry found for $id");
        }

        return $this->resolvers[$id]();
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->resolvers);
    }
}

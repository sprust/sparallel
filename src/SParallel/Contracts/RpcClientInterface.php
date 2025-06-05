<?php

namespace SParallel\Contracts;

use Throwable;

interface RpcClientInterface
{
    /**
     * @param array<string, mixed> $params
     *
     * @return array<int|string, mixed>
     *
     * @throws Throwable
     */
    public function call(string $method, array $params = []): array;
}

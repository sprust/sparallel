<?php

declare(strict_types=1);

namespace SParallel\Objects;

use ArrayIterator;
use Traversable;

class ResultsObject
{
    private bool $hasFailed = false;

    private bool $finished = false;

    /**
     * @param array<mixed, ResultObject> $results
     */
    private array $results;

    /**
     * @param array<mixed, ResultObject> $failed
     */
    private array $failed;

    public function addResult(mixed $key, ResultObject $result): void
    {
        $this->results[$key] = $result;

        if (!is_null($result->error)) {
            $this->failed[$key] = $result;

            $this->hasFailed = true;
        }
    }

    public function hasFailed(): bool
    {
        return $this->hasFailed;
    }

    public function finish(): void
    {
        $this->finished = true;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getResults(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function getFailed(): Traversable
    {
        return new ArrayIterator($this->failed);
    }

    public function count(): int
    {
        return count($this->results);
    }
}

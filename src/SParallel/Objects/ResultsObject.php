<?php

declare(strict_types=1);

namespace SParallel\Objects;

class ResultsObject
{
    private bool $hasFailed = false;

    private bool $finished = false;

    /**
     * @var array<mixed, ResultObject> $results
     */
    private array $results = [];

    /**
     * @var array<mixed, ResultObject> $failed
     */
    private array $failed = [];

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

    /**
     * @return array<ResultObject>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return array<ResultObject>
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    public function count(): int
    {
        return count($this->results);
    }
}

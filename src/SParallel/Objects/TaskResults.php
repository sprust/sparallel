<?php

namespace SParallel\Objects;

class TaskResults
{
    private bool $hasFailed = false;

    private bool $finished = false;

    /**
     * @var array<mixed, TaskResult> $results
     */
    private array $results = [];

    /**
     * @var array<mixed, TaskResult> $failed
     */
    private array $failed = [];

    public function addResult(TaskResult $result): void
    {
        $this->results[$result->key] = $result;

        if (!is_null($result->error)) {
            $this->failed[$result->key] = $result;

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
     * @return array<TaskResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return array<TaskResult>
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

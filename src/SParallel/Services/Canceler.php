<?php

declare(strict_types=1);

namespace SParallel\Services;

use SParallel\Contracts\CancelerInterface;
use SParallel\Exceptions\CancelerException;
use Throwable;

class Canceler
{
    /**
     * @var CancelerInterface[]
     */
    protected array $cancelers = [];

    public function add(CancelerInterface $canceler): static
    {
        $this->cancelers[] = $canceler;

        return $this;
    }

    /**
     * @throws CancelerException
     */
    public function check(): void
    {
        foreach ($this->cancelers as $canceler) {
            try {
                $canceler->check();
            } catch (Throwable $exception) {
                throw new CancelerException(
                    canceler: $canceler,
                    exception: $exception
                );
            }
        }
    }

    public function canceled(): bool
    {
        try {
            $this->check();

            return false;
        } catch (CancelerException) {
            return true;
        }
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Server\Dto;

readonly class ResponseAnswer
{
    public function __construct(
        public string $answer,
    ) {
    }
}

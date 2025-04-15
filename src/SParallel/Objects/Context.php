<?php

declare(strict_types=1);

namespace SParallel\Objects;

use SParallel\Transport\Serializer;
use Throwable;

class Context
{
    /**
     * @var array<string, string>
     */
    protected array $context = [];

    public function add(string $key, mixed $value): static
    {
        $this->context[$key] = Serializer::serialize($value);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $context = [];

        foreach ($this->context as $key => $serializedValue) {
            $context[$key] = $this->get($key);
        }

        return $context;
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->context)) {
            return null;
        }

        $serializedValue = $this->context[$key];

        try {
            $value = Serializer::unSerialize($serializedValue);
        } catch (Throwable) {
            $value = null;
        }

        if (is_null($value)) {
            return null;
        }

        if (is_callable($value)) {
            return $value();
        }

        return $value;
    }

    public function __serialize(): array
    {
        return [
            'context' => $this->context,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->context = $data['context'];
    }
}

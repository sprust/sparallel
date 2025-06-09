<?php

declare(strict_types=1);

namespace SParallel\Transport;

use ReflectionClass;
use ReflectionException;
use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use Throwable;

readonly class ExceptionTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
        //
    }

    public function serialize(Throwable $exception): string
    {
        $reflection = new ReflectionClass($exception);

        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($exception);

            if ($value instanceof Throwable) {
                $value = [
                    '#__exception' => $this->serialize($value),
                ];
            } else {
                $value = $this->serializer->serialize($value);
            }

            $properties[$property->getName()] = $value;
        }

        return json_encode([
            'class' => $exception::class,
            '#__props' => $properties,
        ]);
    }

    public function unserialize(string $data): Throwable
    {
        $data = json_decode($data, true);

        $isArray = is_array($data);

        if (!$isArray || !array_key_exists('class', $data) || !array_key_exists('#__props', $data)) {
            throw new UnserializeException(
                expected: 'array with keys "class", "#__props"',
                got: $isArray ? implode(', ', array_keys($data)) : gettype($data)
            );
        }

        try {
            $reflection = new ReflectionClass($data['class']);

            $exception = $reflection->newInstanceWithoutConstructor();

            $properties = [];

            foreach ($data['#__props'] as $property => $value) {
                if (is_array($value) && array_key_exists('#__exception', $value)) {
                    $value = $this->unserialize($value['#__exception']);
                } else {
                    $value = $this->serializer->unserialize($value);
                }

                $properties[$property] = $value;
            }

            $reflection = new ReflectionClass($exception);

            foreach ($reflection->getProperties() as $property) {
                if (!array_key_exists($property->name, $properties)) {
                    continue;
                }

                if ($property->name === 'trace') {
                    $value = array_map(function ($frame) {
                        return [
                            'file' => $frame['file'] ?? '',
                            'line' => $frame['line'] ?? 0,
                            'function' => $frame['function'] ?? '{main}',
                            'class' => $frame['class'] ?? '',
                            'type' => $frame['type'] ?? '',
                            'args' => $frame['args'] ?? [],
                            'object' => $frame['object'] ?? null
                        ];
                    }, $properties[$property->name]);
                } else {
                    $value = $properties[$property->name];
                }

                $reflection->getProperty($property->name)
                    ->setValue($exception, $value);
            }
        } catch (ReflectionException $exception) {
            throw new \RuntimeException(
                $exception->getMessage(),
                previous: $exception,
            );
        }

        return $exception;
    }
}

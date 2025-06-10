<?php

declare(strict_types=1);

namespace SParallel\Transport;

use ReflectionClass;
use ReflectionException;
use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use Throwable;

/**
 * TODO: check, optimize
 */
readonly class ExceptionTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
        //
    }

    public function serialize(Throwable $exception): string
    {
        $reflection = new ReflectionClass($exception);

        $properties = [
            'class'    => $exception::class,
            'message'  => $exception->getMessage(),
            'code'     => $exception->getCode(),
            'file'     => $exception->getFile(),
            'line'     => $exception->getLine(),
            'trace'    => $exception->getTrace(),
            'previous' => $exception->getPrevious()
                ? $this->serialize($exception->getPrevious())
                : null,
        ];

        $customProperties = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $properties)) {
                continue;
            }

            $value = $property->getValue($exception);

            if ($value instanceof Throwable) {
                $value = [
                    '#__exception' => $this->serialize($value),
                ];
            } else {
                if (is_array($value) && $property->getName() === 'trace') {
                    $value = json_encode($value);
                } else {
                    $value = $this->serializer->serialize($value);
                }
            }

            $customProperties[$property->getName()] = $value;
        }

        $properties['#__props'] = $customProperties;

        return json_encode($properties);
    }

    public function unserialize(string $data): Throwable
    {
        $data = json_decode($data, true);

        $isArray = is_array($data);

        if (!$isArray
            || !array_key_exists('class', $data)
            || !array_key_exists('message', $data)
            || !array_key_exists('code', $data)
            || !array_key_exists('file', $data)
            || !array_key_exists('line', $data)
            || !array_key_exists('trace', $data)
            || !array_key_exists('previous', $data)
            || !array_key_exists('#__props', $data)
        ) {
            if ($isArray) {
                throw new UnserializeException(
                    expected: 'undefined keys "class", "message", "code", "file", "line", "trace", "previous", "#__props"',
                    got: implode(', ', array_keys($data))
                );
            } else {
                throw new UnserializeException(
                    expected: 'array',
                    got: gettype($data)
                );
            }
        }

        try {
            $reflection = new ReflectionClass($data['class']);

            unset($data['class']);

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

            unset($data['#__props']);

            $properties = array_merge($properties, $data);

            $reflection = new ReflectionClass($exception);

            foreach ($reflection->getProperties() as $property) {
                if (!array_key_exists($property->name, $properties)) {
                    continue;
                }

                if ($property->name === 'trace') {
                    $value = array_map(
                        function (array $frame) {
                            return [
                                'file'     => $frame['file'] ?? '',
                                'line'     => $frame['line'] ?? 0,
                                'function' => $frame['function'] ?? '{main}',
                                'class'    => $frame['class'] ?? '',
                                'type'     => $frame['type'] ?? '',
                                'args'     => $frame['args'] ?? [],
                                'object'   => $frame['object'] ?? null,
                            ];
                        },
                        $properties[$property->name]
                    );
                } elseif ($property->name === 'previous') {
                    $value = $properties[$property->name]
                        ? $this->unserialize($properties[$property->name])
                        : null;
                } else {
                    $value = $properties[$property->name];
                }

                unset($properties[$property->name]);

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

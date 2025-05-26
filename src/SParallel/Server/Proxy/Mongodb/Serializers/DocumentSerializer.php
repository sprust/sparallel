<?php

namespace SParallel\Server\Proxy\Mongodb\Serializers;

use DateTimeInterface;
use MongoDB\BSON\UTCDateTimeInterface;

readonly class DocumentSerializer
{
    /**
     * @param array<int|string, mixed> $document
     */
    public function serialize(array $document): string
    {
        $result = [];

        foreach ($document as $key => $value) {
            $this->serializeRecursive(
                result: $result,
                key: $key,
                value: $value,
            );
        }

        return json_encode($result);
    }

    protected function serializeRecursive(array &$result, int|string $key, mixed $value): void
    {
        if (is_scalar($value)) {
            $result[$key] = $value;

            return;
        }

        if (is_object($value)) {
            if ($value instanceof DateTimeInterface) {
                $result[$key] = [
                    '|t_' => 'datetime',
                    '|v_' => $value->format(DATE_RFC3339),
                ];

                return;
            }

            if ($value instanceof UTCDateTimeInterface) {
                $result[$key] = [
                    '|t_' => 'datetime',
                    '|v_' => $value->toDateTime()->format(DATE_RFC3339),
                ];

                return;
            }
        }

        if (is_array($value)) {
            $result[$key] = [];

            foreach ($value as $subKey => $subValue) {
                $this->serializeRecursive(
                    result: $result[$key],
                    key: $subKey,
                    value: $subValue,
                );
            }
        }
    }
}

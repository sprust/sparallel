<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Objects\Context;
use Throwable;

class ContextTransport
{
    public static function serialize(Context $context): string
    {
        return serialize($context);
    }

    public static function unSerialize(?string $data): ?Context
    {
        if (is_null($data)) {
            return null;
        }

        try {
            $context = unserialize($data);
        } catch (Throwable) {
            return null;
        }

        if ($context instanceof Context) {
            return $context;
        }

        return null;
    }
}

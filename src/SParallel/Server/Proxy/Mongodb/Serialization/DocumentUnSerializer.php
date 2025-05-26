<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Serialization;

use MongoDB\BSON\Document;

readonly class DocumentUnSerializer
{
    public function unserialize(string $document): Document
    {
        return Document::fromBSON($document);
    }
}

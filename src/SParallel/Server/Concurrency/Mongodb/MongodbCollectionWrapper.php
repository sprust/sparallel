<?php

declare(strict_types=1);

namespace SParallel\Server\Concurrency\Mongodb;

use Iterator;
use MongoDB\BulkWriteResult;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use SParallel\Contracts\MongodbConnectionUriFactoryInterface;
use Throwable;

/**
 * @mixin Collection
 */
class MongodbCollectionWrapper
{
    protected readonly string $uri;

    protected ?Collection $driverCollection = null;

    public function __construct(
        protected MongodbConnectionUriFactoryInterface $uriFactory,
        protected MongodbClient $mongodbClient,
        protected string $databaseName,
        protected string $collectionName,
        protected int $serverOffUntil = 0
    ) {
        $this->uri = $this->uriFactory->get();
    }

    /**
     * @param array<int|string, mixed>|object $document
     * @param array<string, mixed>            $options
     */
    public function insertOne(array|object $document, array $options = []): InsertOneResult
    {
        if ($this->isServerActive()) {
            try {
                return $this->mongodbClient->insertOne(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    document: (array) $document,
                );
            } catch (Throwable) {
                $this->onServerFailed();
            }
        }

        return $this->getDriverCollection()->insertOne(
            document: $document,
            options: $options
        );
    }

    /**
     * @param array<int|string, mixed>|object $filter
     * @param array<int|string, mixed>|object $update
     * @param array<string, mixed>            $options
     */
    public function updateOne(array|object $filter, array|object $update, array $options = []): UpdateResult
    {
        if ($this->isServerActive()) {
            try {
                return $this->mongodbClient->updateOne(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    filter: (array) $filter,
                    update: (array) $update,
                    options: $options,
                );
            } catch (Throwable) {
                $this->onServerFailed();
            }
        }

        return $this->getDriverCollection()->updateOne(
            filter: $filter,
            update: $update,
            options: $options,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $pipeline
     * @param array<string, mixed>             $options
     */
    public function aggregate(array $pipeline, array $options = []): Iterator
    {
        if ($this->isServerActive()) {
            try {
                return $this->mongodbClient->aggregate(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    pipeline: $pipeline,
                );
            } catch (Throwable) {
                $this->onServerFailed();
            }
        }

        return $this->getDriverCollection()->aggregate(
            pipeline: $pipeline,
            options: $options,
        );
    }

    /**
     * @param array<int|string, mixed> $operations
     * @param array<string, mixed>     $options
     */
    public function bulkWrite(array $operations, array $options = []): BulkWriteResult
    {
        if ($this->isServerActive()) {
            try {
                return $this->mongodbClient->bulkWrite(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    operations: $operations,
                );
            } catch (Throwable) {
                $this->onServerFailed();
            }
        }

        return $this->getDriverCollection()->bulkWrite(
            operations: $operations,
            options: $options,
        );
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->driverCollection->{$name}(...$arguments);
    }

    protected function getDriverCollection(): Collection
    {
        if (is_null($this->driverCollection)) {
            $this->driverCollection = (new Client(uri: $this->uri))->selectDatabase($this->databaseName)
                ->selectCollection($this->collectionName);
        }

        return $this->driverCollection;
    }

    protected function isServerActive(): bool
    {
        if ($this->serverOffUntil === 0) {
            return true;
        }

        if ($this->serverOffUntil > time()) {
            return false;
        }

        $this->serverOffUntil = 0;

        return true;
    }

    protected function onServerFailed(): void
    {
        $this->serverOffUntil = time() + 5;
    }
}

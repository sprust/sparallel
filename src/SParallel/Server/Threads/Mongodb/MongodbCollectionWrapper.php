<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb;

use Iterator;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use SParallel\Contracts\MongodbConnectionUriFactoryInterface;
use Throwable;

/**
 * @mixin Collection
 */
readonly class MongodbCollectionWrapper
{
    private string $uri;
    private string $databaseName;
    private string $collectionName;

    public function __construct(
        protected MongodbConnectionUriFactoryInterface $uriFactory,
        protected Collection $driverCollection,
        protected MongodbClient $mongodbClient,
    ) {
        $this->databaseName   = $this->driverCollection->getDatabaseName();
        $this->collectionName = $this->driverCollection->getCollectionName();

        $this->uri = $this->uriFactory->get();
    }

    public function insertOne(array|object $document, array $options = []): InsertOneResult
    {
        try {
            return $this->mongodbClient->insertOne(
                connection: $this->uri,
                database: $this->databaseName,
                collection: $this->collectionName,
                document: (array) $document,
            );
        } catch (Throwable) {
            // TODO: handle exception
            return $this->driverCollection->insertOne(
                document: $document,
                options: $options
            );
        }
    }

    public function updateOne(array|object $filter, array|object $update, array $options = []): UpdateResult
    {
        try {
            return $this->mongodbClient->updateOne(
                connection: $this->uri,
                database: $this->databaseName,
                collection: $this->collectionName,
                filter: (array) $filter,
                update: (array) $update,
            );
        } catch (Throwable) {
            // TODO: handle exception
            return $this->driverCollection->updateOne(
                filter: $filter,
                update: $update,
                options: $options,
            );
        }
    }

    public function aggregate(array $pipeline, array $options = []): Iterator
    {
        try {
            return $this->mongodbClient->aggregate(
                connection: $this->uri,
                database: $this->databaseName,
                collection: $this->collectionName,
                pipeline: $pipeline,
            );
        } catch (Throwable) {
            // TODO: handle exception
            return $this->driverCollection->aggregate(
                pipeline: $pipeline,
                options: $options,
            );
        }
    }

    public function bulkWrite(array $operations, array $options = []): BulkWriteResult
    {
        try {
            return $this->mongodbClient->bulkWrite(
                connection: $this->uri,
                database: $this->databaseName,
                collection: $this->collectionName,
                operations: $operations,
            );
        } catch (Throwable) {
            // TODO: handle exception
            return $this->driverCollection->bulkWrite(
                operations: $operations,
                options: $options,
            );
        }
    }

    public function __call(string $name, array $arguments)
    {
        return $this->driverCollection->{$name}(...$arguments);
    }
}

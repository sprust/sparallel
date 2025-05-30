<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb;

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
        protected bool $useServer = true
    ) {
        $this->uri = $this->uriFactory->get();
    }

    public function insertOne(array|object $document, array $options = []): InsertOneResult
    {
        if ($this->useServer) {
            try {
                return $this->mongodbClient->insertOne(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    document: (array) $document,
                );
            } catch (Throwable) {
                // TODO: handle exception

                $this->useServer = false;
            }
        }

        return $this->getDriverCollection()->insertOne(
            document: $document,
            options: $options
        );
    }

    public function updateOne(array|object $filter, array|object $update, array $options = []): UpdateResult
    {
        if ($this->useServer) {
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
                // TODO: handle exception

                $this->useServer = false;
            }
        }

        return $this->getDriverCollection()->updateOne(
            filter: $filter,
            update: $update,
            options: $options,
        );
    }

    public function aggregate(array $pipeline, array $options = []): Iterator
    {
        if ($this->useServer) {
            try {
                return $this->mongodbClient->aggregate(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    pipeline: $pipeline,
                );
            } catch (Throwable) {
                // TODO: handle exception

                $this->useServer = false;
            }
        }

        return $this->getDriverCollection()->aggregate(
            pipeline: $pipeline,
            options: $options,
        );
    }

    public function bulkWrite(array $operations, array $options = []): BulkWriteResult
    {
        if ($this->useServer) {
            try {
                return $this->mongodbClient->bulkWrite(
                    connection: $this->uri,
                    database: $this->databaseName,
                    collection: $this->collectionName,
                    operations: $operations,
                );
            } catch (Throwable) {
                // TODO: handle exception

                $this->useServer = false;
            }
        }

        return $this->getDriverCollection()->bulkWrite(
            operations: $operations,
            options: $options,
        );
    }

    public function __call(string $name, array $arguments)
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
}

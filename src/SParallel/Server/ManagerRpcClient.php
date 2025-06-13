<?php

declare(strict_types=1);

namespace SParallel\Server;

use DateTimeImmutable;
use SParallel\Contracts\RpcClientInterface;
use SParallel\Server\Dto\ResponseAnswer;
use SParallel\Server\Dto\Stats\ServerStats;
use SParallel\Server\Dto\Stats\SystemInfo;
use SParallel\Server\Dto\Stats\TasksInfo;
use SParallel\Server\Dto\Stats\WorkersData;
use SParallel\Server\Dto\Stats\WorkersInfo;
use Throwable;

readonly class ManagerRpcClient
{
    public function __construct(protected RpcClientInterface $rpcClient)
    {
    }

    /**
     * @throws Throwable
     */
    public function sleep(): ResponseAnswer
    {
        $response = $this->rpcClient->call('ManagerServer.Sleep', [
            'Message' => 'sleep, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * @throws Throwable
     */
    public function wakeUp(): ResponseAnswer
    {
        $response = $this->rpcClient->call('ManagerServer.WakeUp', [
            'Message' => 'wake up, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * @throws Throwable
     */
    public function stop(): ResponseAnswer
    {
        $response = $this->rpcClient->call('ManagerServer.Stop', [
            'Message' => 'stop, please.',
        ]);

        return new ResponseAnswer(
            answer: $response['Answer']
        );
    }

    /**
     * Return JSON
     *
     * @throws Throwable
     */
    public function stats(): ServerStats
    {
        $response = $this->rpcClient->call('ManagerServer.Stats', [
            'Message' => 'get stats, please.',
        ]);

        $data = json_decode(
            json: $response['Json'],
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );

        $system         = $data['system'];
        $workers        = $data['workers'];
        $workersWorkers = $workers['Workers'];
        $workersTasks   = $workers['Tasks'];

        return new ServerStats(
            dateTime: new DateTimeImmutable($data['dateTime']),
            system: new SystemInfo(
                numGoroutine: $system['NumGoroutine'],
                allocMiB: $system['AllocMiB'],
                totalAllocMiB: $system['TotalAllocMiB'],
                sysMiB: $system['SysMiB'],
                numGC: $system['NumGC']
            ),
            workers: new WorkersData(
                workers: new WorkersInfo(
                    count: $workersWorkers['Count'],
                    freeCount: $workersWorkers['FreeCount'],
                    busyCount: $workersWorkers['BusyCount'],
                    loadPercent: $workersWorkers['LoadPercent'],
                    addedCount: $workersWorkers['AddedCount'],
                    tookCount: $workersWorkers['TookCount'],
                    freedCount: $workersWorkers['FreedCount'],
                    deletedCount: $workersWorkers['DeletedCount'],
                ),
                tasks: new TasksInfo(
                    waitingCount: $workersTasks['WaitingCount'],
                    finishedCount: $workersTasks['FinishedCount'],
                    addedTotalCount: $workersTasks['AddedTotalCount'],
                    reAddedTotalCount: $workersTasks['ReAddedTotalCount'],
                    tookTotalCount: $workersTasks['TookTotalCount'],
                    finishedTotalCount: $workersTasks['FinishedTotalCount'],
                    successTotalCount: $workersTasks['SuccessTotalCount'],
                    errorTotalCount: $workersTasks['ErrorTotalCount'],
                    timeoutTotalCount: $workersTasks['TimeoutTotalCount'],
                )
            )
        );
    }
}

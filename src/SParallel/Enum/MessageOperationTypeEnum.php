<?php

declare(strict_types=1);

namespace SParallel\Enum;

enum MessageOperationTypeEnum: string
{
    case Job = 'job';
    case GetJob = 'gjob';
    case Response = 'resp';
}

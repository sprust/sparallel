<?php

declare(strict_types=1);

namespace SParallel\Enum;

enum MessageOperationTypeEnum: string
{
    case Job = 'jb';
    case TaskStart = 'ts';
    case TaskFinished = 'tf';
    case GetJob = 'gjb';
    case Response = 'res';
}

<?php

declare(strict_types=1);

namespace SParallel\Enum;

enum MessageOperationTypeEnum: string
{
    case Task = 't';
    case StartTask = 'ts';
    case TaskFinished = 'tf';
    case GetTask = 'tg';
    case Response = 'res';
}

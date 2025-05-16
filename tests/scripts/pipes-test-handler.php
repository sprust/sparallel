<?php

declare(strict_types=1);

while (true) {
    $data = fread(STDIN, 1024);

    if ($data === false) {
        continue;
    }

    //$seconds = mt_rand(0, 3);
    //
    //sleep($seconds);

    fwrite(STDOUT, "pong: " . str_repeat('0', 2024));

    break;
}


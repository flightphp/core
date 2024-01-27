<?php

declare(strict_types=1);

$path = file_exists(__DIR__ . '/../vendor/autoload.php')
    ? __DIR__ . '/../vendor/autoload.php'
    : __DIR__ . '/../flight/autoload.php';

require_once($path);

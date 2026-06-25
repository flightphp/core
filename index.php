<?php

declare(strict_types=1);

require_once __DIR__ . '/flight/autoload.php';

Flight::route('/', function () {
    echo 'hello world!';
});

Flight::start();

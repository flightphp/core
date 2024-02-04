<?php

// require 'flight/Flight.php';
require 'flight/autoload.php';

Flight::route('/', function () {
    echo 'hello world!';
});

Flight::start();

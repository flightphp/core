<?php
use flight\Flight;

Flight::route('/', function(){
    echo 'hello world!';
});

Flight::start();

<?php
//require 'flight/Flight.php';
require 'vendor/autoload.php';
use flight\Flight as F;

F::route('/', function(){
    echo 'hello world!';
});

F::start();
?>

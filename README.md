![PHPStan: enabled](https://user-images.githubusercontent.com/104888/50957476-9c4acb80-14be-11e9-88ce-6447364dc1bb.png)
![PHPStan: level 6](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat)
![Matrix](https://img.shields.io/matrix/flight-php-framework%3Amatrix.org?server_fqdn=matrix.org&style=social&logo=matrix)
[![Hit Count](https://hits.dwyl.com/flightphp/core.svg?style=flat-square&show=unique)](http://hits.dwyl.com/flightphp/core)

# What is Flight?

Flight is a fast, simple, extensible framework for PHP. Flight enables you to
quickly and easily build RESTful web applications.

Chat with us on Matrix IRC [#flight-php-framework:matrix.org](https://matrix.to/#/#flight-php-framework:matrix.org)

# Basic Usage

```php
// if installed with composer
require 'vendor/autoload.php';
// or if installed manually by zip file
// require 'flight/Flight.php';

Flight::route('/', function() {
  echo 'hello world!';
});

Flight::start();
```
# More information and examples
We have our own documentation website that is being run by Flight. Please check the remaining documentation on our website [learn more](https://docs.flightphp.com).

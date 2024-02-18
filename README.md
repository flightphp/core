![PHPStan: enabled](https://user-images.githubusercontent.com/104888/50957476-9c4acb80-14be-11e9-88ce-6447364dc1bb.png)
![PHPStan: level 6](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat)
![Matrix](https://img.shields.io/matrix/flight-php-framework%3Amatrix.org?server_fqdn=matrix.org&style=social&logo=matrix)

# What is Flight?

Flight is a fast, simple, extensible framework for PHP. Flight enables you to
quickly and easily build RESTful web applications.

# Basic Usage

```php
// if installed with composer
require 'vendor/autoload.php';
// or if installed manually by zip file
// require 'flight/Flight.php';

Flight::route('/', function () {
  echo 'hello world!';
});

Flight::start();
```

## Skeleton App

You can also install a skeleton app. Go to [flightphp/skeleton](https://github.com/flightphp/skeleton) for instructions on how to get started!

# Documentation

We have our own documentation website that is built with Flight (naturally). Learn more about the framework at [docs.flightphp.com](https://docs.flightphp.com).

# Community

Chat with us on Matrix IRC [#flight-php-framework:matrix.org](https://matrix.to/#/#flight-php-framework:matrix.org)

# Requirements

> [!IMPORTANT]
> Flight requires `PHP 7.4` or greater.

**Note:** PHP 7.4 is supported because at the current time of writing (2024) PHP 7.4 is the default version for some LTS Linux distributions. Forcing a move to PHP >8 would cause a lot of heartburn for those users.

The framework also supports PHP >8.

# License

Flight is released under the [MIT](http://docs.flightphp.com/license) license.

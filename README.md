[![Version](https://poser.pugx.org/flightphp/core/version)](https://packagist.org/packages/flightphp/core)
[![Monthly Downloads](https://poser.pugx.org/flightphp/core/d/monthly)](https://packagist.org/packages/flightphp/core)
![PHPStan: Level 6](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat)
[![License](https://poser.pugx.org/flightphp/core/license)](https://packagist.org/packages/flightphp/core)
[![PHP Version Require](https://poser.pugx.org/flightphp/core/require/php)](https://packagist.org/packages/flightphp/core)
![Matrix](https://img.shields.io/matrix/flight-php-framework%3Amatrix.org?server_fqdn=matrix.org&style=social&logo=matrix)
[![](https://dcbadge.limes.pink/api/server/https://discord.gg/Ysr4zqHfbX)](https://discord.gg/Ysr4zqHfbX)

# What is Flight?

Flight is a fast, simple, extensible framework for PHP. Flight enables you to
quickly and easily build RESTful web applications. Flight also has zero dependencies.

# Basic Usage

First install it with Composer

```
composer require flightphp/core
```

or you can download a zip of this repo. Then you would have a basic `index.php` file like the following:

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

## Is it fast?

Yes! Flight is fast. It is one of the fastest PHP frameworks available. You can see all the benchmarks at [TechEmpower](https://www.techempower.com/benchmarks/#section=data-r18&hw=ph&test=frameworks)

See the benchmark below with some other popular PHP frameworks. This is measured in requests processed within the same timeframe. 

| Framework | Plaintext Requests| JSON Requests|
| --------- | ------------ | ------------ |
| Flight      | 190,421    | 182,491 |
| Yii         | 145,749    | 131,434 |
| Fat-Free    | 139,238	   | 133,952 |
| Slim        | 89,588     | 87,348  |
| Phalcon     | 95,911     | 87,675  |
| Symfony     | 65,053     | 63,237  |
| Lumen	      | 40,572     | 39,700  |
| Laravel     | 26,657     | 26,901  |
| CodeIgniter | 20,628     | 19,901  |

## Skeleton App

You can also install a skeleton app. Go to [flightphp/skeleton](https://github.com/flightphp/skeleton) for instructions on how to get started!

# Documentation

We have our own documentation website that is built with Flight (naturally). Learn more about the framework at [docs.flightphp.com](https://docs.flightphp.com).

# Community

Chat with us on Matrix IRC [#flight-php-framework:matrix.org](https://matrix.to/#/#flight-php-framework:matrix.org)

[![](https://dcbadge.limes.pink/api/server/https://discord.gg/Ysr4zqHfbX)](https://discord.gg/Ysr4zqHfbX)

# Upgrading From v2

If you have a current project on v2, you should be able to upgrade to v3 with no issues depending on how your project was built. If there are any issues with upgrade, they are documented in the [migrating to v3](https://docs.flightphp.com/learn/migrating-to-v3) documentation page. It is the intention of Flight to maintain longterm stability of the project and to not add rewrites with major version changes.

# Requirements

> [!IMPORTANT]
> Flight requires `PHP 7.4` or greater.

**Note:** PHP 7.4 is supported because at the current time of writing (2024) PHP 7.4 is the default version for some LTS Linux distributions. Forcing a move to PHP >8 would cause a lot of heartburn for those users.

The framework also supports PHP >8.

# Roadmap

To see the current and future roadmap for the Flight Framework, visit the [project roadmap](https://github.com/orgs/flightphp/projects/1/views/1)

# License

Flight is released under the [MIT](http://docs.flightphp.com/license) license.

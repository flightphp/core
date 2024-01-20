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

# Want to setup a Skeleton/Boilerplate project quickly?

Head over to the [flightphp/skeleton](https://github.com/flightphp/skeleton) repo to get started!

# Requirements

Flight requires `PHP 7.4` or greater.

# License

Flight is released under the [MIT](http://docs.flightphp.com/license) license.

# Installation

**1\. Download the files.**

If you're using [Composer](https://getcomposer.org), you can run the following
command:

```bash
composer require flightphp/core
```

OR you can [download](https://github.com/flightphp/core/archive/master.zip)
them directly and extract them to your web directory.

**2\. Configure your webserver.**

For *Apache*, edit your `.htaccess` file with the following:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

> **Note**: If you need to use flight in a subdirectory add the line
> `RewriteBase /subdir/` just after `RewriteEngine On`.
> **Note**: If you want to protect all server files, like a db or env file.
> Put this in your `.htaccess` file:

```
RewriteEngine On
RewriteRule ^(.*)$ index.php
```

For *Nginx*, add the following to your server declaration:

```
server {
  location / {
    try_files $uri $uri/ /index.php;
  }
}
```
**3\. Create your `index.php` file.**

First include the framework.

```php
require 'flight/Flight.php';
```

If you're using Composer, run the autoloader instead.

```php
require 'vendor/autoload.php';
```

Then define a route and assign a function to handle the request.

```php
Flight::route('/', function () {
  echo 'hello world!';
});
```

Finally, start the framework.

```php
Flight::start();
```

## Skeleton App

Additionally you could install a skeleton app. Go to [flightphp/skeleton](https://github.com/flightphp/skeleton) for instructions on how to get started!

# Routing

Routing in Flight is done by matching a URL pattern with a callback function.

```php
Flight::route('/', function () {
  echo 'hello world!';
});
```

The callback can be any object that is callable. So you can use a regular function:

```php
function hello() {
  echo 'hello world!';
}

Flight::route('/', 'hello');
```

Or a class method:

```php
class Greeting {
  static function hello() {
    echo 'hello world!';
  }
}

Flight::route('/', [Greeting::class, 'hello']);
```

Or an object method:

```php
class Greeting {
  private $name;

  function __construct() {
    $this->name = 'John Doe';
  }

  function hello() {
    echo "Hello, $this->name!";
  }
}

$greeting = new Greeting;

Flight::route('/', [$greeting, 'hello']);
```

Routes are matched in the order they are defined. The first route to match a
request will be invoked.

# Configuration

You can customize certain behaviors of Flight by setting configuration values
through the `set` method.

```php
Flight::set('flight.log_errors', true);
```

The following is a list of all the available configuration settings:

- **flight.base_url** - Override the base url of the request. (default: null)
- **flight.case_sensitive** - Case sensitive matching for URLs. (default: false)
- **flight.handle_errors** - Allow Flight to handle all errors internally. (default: true)
- **flight.log_errors** - Log errors to the web server's error log file. (default: false)
- **flight.views.path** - Directory containing view template files. (default: ./views)
- **flight.views.extension** - View template file extension. (default: .php)

# Framework Methods

Flight is designed to be easy to use and understand. The following is the complete
set of methods for the framework. It consists of core methods, which are regular
static methods, and extensible methods, which are mapped methods that can be filtered
or overridden.

## Core Methods

```php
Flight::map(string $name, callable $callback, bool $pass_route = false) // Creates a custom framework method.
Flight::register(string $name, string $class, array $params = [], ?callable $callback = null) // Registers a class to a framework method.
Flight::before(string $name, callable $callback) // Adds a filter before a framework method.
Flight::after(string $name, callable $callback) // Adds a filter after a framework method.
Flight::path(string $path) // Adds a path for autoloading classes.
Flight::get(string $key) // Gets a variable.
Flight::set(string $key, mixed $value) // Sets a variable.
Flight::has(string $key) // Checks if a variable is set.
Flight::clear(array|string $key = []) // Clears a variable.
Flight::init() // Initializes the framework to its default settings.
Flight::app() // Gets the application object instance
```

## Extensible Methods

```php
Flight::start() // Starts the framework.
Flight::stop() // Stops the framework and sends a response.
Flight::halt(int $code = 200, string $message = '') // Stop the framework with an optional status code and message.
Flight::route(string $pattern, callable $callback, bool $pass_route = false) // Maps a URL pattern to a callback.
Flight::group(string $pattern, callable $callback) // Creates groupping for urls, pattern must be a string.
Flight::redirect(string $url, int $code) // Redirects to another URL.
Flight::render(string $file, array $data, ?string $key = null) // Renders a template file.
Flight::error(Throwable $error) // Sends an HTTP 500 response.
Flight::notFound() // Sends an HTTP 404 response.
Flight::etag(string $id, string $type = 'string') // Performs ETag HTTP caching.
Flight::lastModified(int $time) // Performs last modified HTTP caching.
Flight::json(mixed $data, int $code = 200, bool $encode = true, string $charset = 'utf8', int $option) // Sends a JSON response.
Flight::jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = 'utf8', int $option) // Sends a JSONP response.
```

Any custom methods added with `map` and `register` can also be filtered.

# More detailed information
We have our own documentation website that is being run by Flight. Please check the remaining documentation on our website.
[Learn more](https://docs.flightphp.com/learn)

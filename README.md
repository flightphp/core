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

[Learn more](https://docs.flightphp.com/learn)

# Want to setup a Skeleton/Boilerplate project quickly?

Head over to the [flightphp/skeleton](https://github.com/flightphp/skeleton) repo to get started!

# Need some inspiration?

While these are not officially sponsored by the FlightPHP Team, these could give you ideas on how to structure your own projects that are built with Flight!

- https://github.com/markhughes/flight-skeleton - Basic Skeleton App
- https://github.com/Skayo/FlightWiki - Example Wiki
- https://github.com/itinnovator/myphp-app - The IT-Innovator PHP Framework Application
- https://github.com/casgin/LittleEducationalCMS - LittleEducationalCMS (Spanish)
- https://github.com/chiccomagnus/PGAPI - Italian Yellow Pages API
- https://github.com/recepuncu/cms - Generic Content Management System (with....very little documentation)
- https://github.com/ycrao/tinyme - A tiny php framework based on Flight and medoo.
- https://github.com/paddypei/Flight-MVC - Example MVC Application

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

## Method Routing

By default, route patterns are matched against all request methods. You can respond
to specific methods by placing an identifier before the URL.

```php
Flight::route('GET /', function () {
  echo 'I received a GET request.';
});

Flight::route('POST /', function () {
  echo 'I received a POST request.';
});
```

You can also map multiple methods to a single callback by using a `|` delimiter:

```php
Flight::route('GET|POST /', function () {
  echo 'I received either a GET or a POST request.';
});
```

## Regular Expressions

You can use regular expressions in your routes:

```php
Flight::route('/user/[0-9]+', function () {
  // This will match /user/1234
});
```

## Named Parameters

You can specify named parameters in your routes which will be passed along to
your callback function.

```php
Flight::route('/@name/@id', function (string $name, string $id) {
  echo "hello, $name ($id)!";
});
```

You can also include regular expressions with your named parameters by using
the `:` delimiter:

```php
Flight::route('/@name/@id:[0-9]{3}', function (string $name, string $id) {
  // This will match /bob/123
  // But will not match /bob/12345
});
```

Matching regex groups `()` with named parameters isn't supported.

## Optional Parameters

You can specify named parameters that are optional for matching by wrapping
segments in parentheses.

```php
Flight::route(
  '/blog(/@year(/@month(/@day)))',
  function(?string $year, ?string $month, ?string $day) {
    // This will match the following URLS:
    // /blog/2012/12/10
    // /blog/2012/12
    // /blog/2012
    // /blog
  }
);
```

Any optional parameters that are not matched will be passed in as NULL.

## Wildcards

Matching is only done on individual URL segments. If you want to match multiple
segments you can use the `*` wildcard.

```php
Flight::route('/blog/*', function () {
  // This will match /blog/2000/02/01
});
```

To route all requests to a single callback, you can do:

```php
Flight::route('*', function () {
  // Do something
});
```

## Passing

You can pass execution on to the next matching route by returning `true` from
your callback function.

```php
Flight::route('/user/@name', function (string $name) {
  // Check some condition
  if ($name !== "Bob") {
    // Continue to next route
    return true;
  }
});

Flight::route('/user/*', function () {
  // This will get called
});
```

## Route Info

If you want to inspect the matching route information, you can request for the route
object to be passed to your callback by passing in `true` as the third parameter in
the route method. The route object will always be the last parameter passed to your
callback function.

```php
Flight::route('/', function(\flight\net\Route $route) {
  // Array of HTTP methods matched against
  $route->methods;

  // Array of named parameters
  $route->params;

  // Matching regular expression
  $route->regex;

  // Contains the contents of any '*' used in the URL pattern
  $route->splat;
}, true);
```

## Route Grouping

There may be times when you want to group related routes together (such as `/api/v1`).
You can do this by using the `group` method:

```php
Flight::group('/api/v1', function () {
  Flight::route('/users', function () {
	// Matches /api/v1/users
  });

  Flight::route('/posts', function () {
	// Matches /api/v1/posts
  });
});
```

You can even nest groups of groups:

```php
Flight::group('/api', function () {
  Flight::group('/v1', function () {
	// Flight::get() gets variables, it doesn't set a route! See object context below
	Flight::route('GET /users', function () {
	  // Matches GET /api/v1/users
	});

	Flight::post('/posts', function () {
	  // Matches POST /api/v1/posts
	});

	Flight::put('/posts/1', function () {
	  // Matches PUT /api/v1/posts
	});
  });
  Flight::group('/v2', function () {

	// Flight::get() gets variables, it doesn't set a route! See object context below
	Flight::route('GET /users', function () {
	  // Matches GET /api/v2/users
	});
  });
});
```

### Grouping with Object Context

You can still use route grouping with the `Engine` object in the following way:

```php
$app = new \flight\Engine();
$app->group('/api/v1', function (Router $router) {
  $router->get('/users', function () {
	// Matches GET /api/v1/users
  });

  $router->post('/posts', function () {
	// Matches POST /api/v1/posts
  });
});
```

## Route Aliasing

You can assign an alias to a route, so that the URL can dynamically be generated later in your code (like a template for instance).

```php
Flight::route('/users/@id', function($id) { echo 'user:'.$id; }, false, 'user_view');

// later in code somewhere
Flight::getUrl('user_view', [ 'id' => 5 ]); // will return '/users/5'
```

This is especially helpful if your URL happens to change. In the above example, lets say that users was moved to `/admin/users/@id` instead.
With aliasing in place, you don't have to change anywhere you reference the alias because the alias will now return `/admin/users/5` like in the 
example above.

Route aliasing still works in groups as well:

```php
Flight::group('/users', function() {
    Flight::route('/@id', function($id) { echo 'user:'.$id; }, false, 'user_view');
});


// later in code somewhere
Flight::getUrl('user_view', [ 'id' => 5 ]); // will return '/users/5'
```

## Route Middleware
Flight supports route and group route middleware. Middleware is a function that is executed before (or after) the route callback. This is a great way to add API authentication checks in your code, or to validate that the user has permission to access the route.

Here's a basic example:

```php
// If you only supply an anonymous function, it will be executed before the route callback. 
// there are no "after" middleware functions except for classes (see below)
Flight::route('/path', function() { echo ' Here I am!'; })->addMiddleware(function() {
	echo 'Middleware first!';
});

Flight::start();

// This will output "Middleware first! Here I am!"
```

There are some very important notes about middleware that you should be aware of before you use them:
- Middleware functions are executed in the order they are added to the route. The execution is similar to how [Slim Framework handles this](https://www.slimframework.com/docs/v4/concepts/middleware.html#how-does-middleware-work).
   - Befores are executed in the order added, and Afters are executed in reverse order.
- If your middleware function returns false, all execution is stopped and a 403 Forbidden error is thrown. You'll probably want to handle this more gracefully with a `Flight::redirect()` or something similar.
- If you need parameters from your route, they will be passed in a single array to your middleware function. (`function($params) { ... }` or `public function before($params) {}`). The reason for this is that you can structure your parameters into groups and in some of those groups, your parameters may actually show up in a different order which would break the middleware function by referring to the wrong parameter. This way, you can access them by name instead of position.

### Middleware Classes

Middleware can be registered as a class as well. If you need the "after" functionality, you must use a class.

```php
class MyMiddleware {
	public function before($params) {
		echo 'Middleware first!';
	}
	
	public function after($params) {
		echo 'Middleware last!';
	}
}

$MyMiddleware = new MyMiddleware();
Flight::route('/path', function() { echo ' Here I am! '; })->addMiddleware($MyMiddleware); // also ->addMiddleware([ $MyMiddleware, $MyMiddleware2 ]);

Flight::start();

// This will display "Middleware first! Here I am! Middleware last!"
```

### Middleware Groups

You can add a route group, and then every route in that group will have the same middleware as well. This is useful if you need to group a bunch of routes by say an Auth middleware to check the API key in the header.

```php

// added at the end of the group method
Flight::group('/api', function() {
    Flight::route('/users', function() { echo 'users'; }, false, 'users');
	Flight::route('/users/@id', function($id) { echo 'user:'.$id; }, false, 'user_view');
}, [ new ApiAuthMiddleware() ]);
```

# Extending

Flight is designed to be an extensible framework. The framework comes with a set
of default methods and components, but it allows you to map your own methods,
register your own classes, or even override existing classes and methods.

## Mapping Methods

To map your own custom method, you use the `map` function:

```php
// Map your method
Flight::map('hello', function (string $name) {
  echo "hello $name!";
});

// Call your custom method
Flight::hello('Bob');
```

## Registering Classes

To register your own class, you use the `register` function:

```php
// Register your class
Flight::register('user', User::class);

// Get an instance of your class
$user = Flight::user();
```

The register method also allows you to pass along parameters to your class
constructor. So when you load your custom class, it will come pre-initialized.
You can define the constructor parameters by passing in an additional array.
Here's an example of loading a database connection:

```php
// Register class with constructor parameters
Flight::register('db', PDO::class, ['mysql:host=localhost;dbname=test', 'user', 'pass']);

// Get an instance of your class
// This will create an object with the defined parameters
//
// new PDO('mysql:host=localhost;dbname=test','user','pass');
//
$db = Flight::db();
```

If you pass in an additional callback parameter, it will be executed immediately
after class construction. This allows you to perform any set up procedures for your
new object. The callback function takes one parameter, an instance of the new object.

```php
// The callback will be passed the object that was constructed
Flight::register(
  'db',
  PDO::class,
  ['mysql:host=localhost;dbname=test', 'user', 'pass'],
  function (PDO $db) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
);
```

By default, every time you load your class you will get a shared instance.
To get a new instance of a class, simply pass in `false` as a parameter:

```php
// Shared instance of the class
$shared = Flight::db();

// New instance of the class
$new = Flight::db(false);
```

Keep in mind that mapped methods have precedence over registered classes. If you
declare both using the same name, only the mapped method will be invoked.

## PDO Helper Class

Flight comes with a helper class for PDO. It allows you to easily query your database
with all the prepared/execute/fetchAll() wackiness. It greatly simplifies how you can 
query your database.

```php
// Register the PDO helper class
Flight::register('db', \flight\database\PdoWrapper::class, ['mysql:host=localhost;dbname=cool_db_name', 'user', 'pass', [
		PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'utf8mb4\'',
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_STRINGIFY_FETCHES => false,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	]
]);

Flight::route('/users', function () {
	// Get all users
	$users = Flight::db()->fetchAll('SELECT * FROM users');

	// Stream all users
	$statement = Flight::db()->runQuery('SELECT * FROM users');
	while ($user = $statement->fetch()) {
		echo $user['name'];
	}
	
	// Get a single user
	$user = Flight::db()->fetchRow('SELECT * FROM users WHERE id = ?', [123]);

	// Get a single value
	$count = Flight::db()->fetchField('SELECT COUNT(*) FROM users');

	// Special IN() syntax to help out (make sure IN is in caps)
	$users = Flight::db()->fetchAll('SELECT * FROM users WHERE id IN (?)', [[1,2,3,4,5]]);
	// you could also do this
	$users = Flight::db()->fetchAll('SELECT * FROM users WHERE id IN (?)', [ '1,2,3,4,5']);
	
	// Insert a new user
	Flight::db()->runQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['Bob', 'bob@example.com']);
	$insert_id = Flight::db()->lastInsertId();

	// Update a user
	Flight::db()->runQuery("UPDATE users SET name = ? WHERE id = ?", ['Bob', 123]);

	// Delete a user
	Flight::db()->runQuery("DELETE FROM users WHERE id = ?", [123]);

	// Get the number of affected rows
	$statement = Flight::db()->runQuery("UPDATE users SET name = ? WHERE name = ?", ['Bob', 'Sally']);
	$affected_rows = $statement->rowCount();

});
```

# Overriding

Flight allows you to override its default functionality to suit your own needs,
without having to modify any code.

For example, when Flight cannot match a URL to a route, it invokes the `notFound`
method which sends a generic `HTTP 404` response. You can override this behavior
by using the `map` method:

```php
Flight::map('notFound', function() {
  // Display custom 404 page
  include 'errors/404.html';
});
```

Flight also allows you to replace core components of the framework.
For example you can replace the default Router class with your own custom class:

```php
// Register your custom class
Flight::register('router', MyRouter::class);

// When Flight loads the Router instance, it will load your class
$myrouter = Flight::router();
```

Framework methods like `map` and `register` however cannot be overridden. You will
get an error if you try to do so.

# Filtering

Flight allows you to filter methods before and after they are called. There are no
predefined hooks you need to memorize. You can filter any of the default framework
methods as well as any custom methods that you've mapped.

A filter function looks like this:

```php
function (array &$params, string &$output): bool {
  // Filter code
}
```

Using the passed in variables you can manipulate the input parameters and/or the output.

You can have a filter run before a method by doing:

```php
Flight::before('start', function (array &$params, string &$output): bool {
  // Do something
});
```

You can have a filter run after a method by doing:

```php
Flight::after('start', function (array &$params, string &$output): bool {
  // Do something
});
```

You can add as many filters as you want to any method. They will be called in the
order that they are declared.

Here's an example of the filtering process:

```php
// Map a custom method
Flight::map('hello', function (string $name) {
  return "Hello, $name!";
});

// Add a before filter
Flight::before('hello', function (array &$params, string &$output): bool {
  // Manipulate the parameter
  $params[0] = 'Fred';
  return true;
});

// Add an after filter
Flight::after('hello', function (array &$params, string &$output): bool {
  // Manipulate the output
  $output .= " Have a nice day!";
  return true;
});

// Invoke the custom method
echo Flight::hello('Bob');
```

This should display:

```
Hello Fred! Have a nice day!
```

If you have defined multiple filters, you can break the chain by returning `false`
in any of your filter functions:

```php
Flight::before('start', function (array &$params, string &$output): bool {
  echo 'one';
  return true;
});

Flight::before('start', function (array &$params, string &$output): bool {
  echo 'two';

  // This will end the chain
  return false;
});

// This will not get called
Flight::before('start', function (array &$params, string &$output): bool {
  echo 'three';
  return true;
});
```

Note, core methods such as `map` and `register` cannot be filtered because they
are called directly and not invoked dynamically.

# Variables

Flight allows you to save variables so that they can be used anywhere in your application.

```php
// Save your variable
Flight::set('id', 123);

// Elsewhere in your application
$id = Flight::get('id');
```
To see if a variable has been set you can do:

```php
if (Flight::has('id')) {
  // Do something
}
```

You can clear a variable by doing:

```php
// Clears the id variable
Flight::clear('id');

// Clears all variables
Flight::clear();
```

Flight also uses variables for configuration purposes.

```php
Flight::set('flight.log_errors', true);
```

# Views

Flight provides some basic templating functionality by default. To display a view
template call the `render` method with the name of the template file and optional
template data:

```php
Flight::render('hello.php', ['name' => 'Bob']);
```

The template data you pass in is automatically injected into the template and can
be reference like a local variable. Template files are simply PHP files. If the
content of the `hello.php` template file is:

```php
Hello, <?= $name ?>!
```

The output would be:

```
Hello, Bob!
```

You can also manually set view variables by using the set method:

```php
Flight::view()->set('name', 'Bob');
```

The variable `name` is now available across all your views. So you can simply do:

```php
Flight::render('hello');
```

Note that when specifying the name of the template in the render method, you can
leave out the `.php` extension.

By default Flight will look for a `views` directory for template files. You can
set an alternate path for your templates by setting the following config:

```php
Flight::set('flight.views.path', '/path/to/views');
```

## Layouts

It is common for websites to have a single layout template file with interchanging
content. To render content to be used in a layout, you can pass in an optional
parameter to the `render` method.

```php
Flight::render('header', ['heading' => 'Hello'], 'headerContent');
Flight::render('body', ['body' => 'World'], 'bodyContent');
```

Your view will then have saved variables called `headerContent` and `bodyContent`.
You can then render your layout by doing:

```php
Flight::render('layout', ['title' => 'Home Page']);
```

If the template files looks like this:

`header.php`:

```php
<h1><?= $heading ?></h1>
```

`body.php`:

```php
<div><?= $body ?></div>
```

`layout.php`:

```php
<html>
  <head>
    <title><?= $title ?></title>
  </head>
  <body>
    <?= $headerContent ?>
    <?= $bodyContent ?>
  </body>
</html>
```

The output would be:
```html
<html>
  <head>
    <title>Home Page</title>
  </head>
  <body>
    <h1>Hello</h1>
    <div>World</div>
  </body>
</html>
```

## Custom Views

Flight allows you to swap out the default view engine simply by registering your
own view class. Here's how you would use the [Smarty](http://www.smarty.net/)
template engine for your views:

```php
// Load Smarty library
require './Smarty/libs/Smarty.class.php';

// Register Smarty as the view class
// Also pass a callback function to configure Smarty on load
Flight::register('view', Smarty::class, [], function (Smarty $smarty) {
  $smarty->setTemplateDir('./templates/');
  $smarty->setCompileDir('./templates_c/');
  $smarty->setConfigDir('./config/');
  $smarty->setCacheDir('./cache/');
});

// Assign template data
Flight::view()->assign('name', 'Bob');

// Display the template
Flight::view()->display('hello.tpl');
```

For completeness, you should also override Flight's default render method:

```php
Flight::map('render', function(string $template, array $data): void {
  Flight::view()->assign($data);
  Flight::view()->display($template);
});
```
# Error Handling

## Errors and Exceptions

All errors and exceptions are caught by Flight and passed to the `error` method.
The default behavior is to send a generic `HTTP 500 Internal Server Error`
response with some error information.

You can override this behavior for your own needs:

```php
Flight::map('error', function (Throwable $error) {
  // Handle error
  echo $error->getTraceAsString();
});
```

By default errors are not logged to the web server. You can enable this by
changing the config:

```php
Flight::set('flight.log_errors', true);
```

## Not Found

When a URL can't be found, Flight calls the `notFound` method. The default
behavior is to send an `HTTP 404 Not Found` response with a simple message.

You can override this behavior for your own needs:

```php
Flight::map('notFound', function () {
  // Handle not found
});
```

# Redirects

You can redirect the current request by using the `redirect` method and passing
in a new URL:

```php
Flight::redirect('/new/location');
```

By default Flight sends a HTTP 303 status code. You can optionally set a
custom code:

```php
Flight::redirect('/new/location', 401);
```

# Requests

Flight encapsulates the HTTP request into a single object, which can be
accessed by doing:

```php
$request = Flight::request();
```

The request object provides the following properties:

- **url** - The URL being requested
- **base** - The parent subdirectory of the URL
- **method** - The request method (GET, POST, PUT, DELETE)
- **referrer** - The referrer URL
- **ip** - IP address of the client
- **ajax** - Whether the request is an AJAX request
- **scheme** - The server protocol (http, https)
- **user_agent** - Browser information
- **type** - The content type
- **length** - The content length
- **query** - Query string parameters
- **data** - Post data or JSON data
- **cookies** - Cookie data
- **files** - Uploaded files
- **secure** - Whether the connection is secure
- **accept** - HTTP accept parameters
- **proxy_ip** - Proxy IP address of the client
- **host** - The request host name

You can access the `query`, `data`, `cookies`, and `files` properties
as arrays or objects.

So, to get a query string parameter, you can do:

```php
$id = Flight::request()->query['id'];
```

Or you can do:

```php
$id = Flight::request()->query->id;
```

## RAW Request Body

To get the raw HTTP request body, for example when dealing with PUT requests,
you can do:

```php
$body = Flight::request()->getBody();
```

## JSON Input

If you send a request with the type `application/json` and the data `{"id": 123}`
it will be available from the `data` property:

```php
$id = Flight::request()->data->id;
```

# HTTP Caching

Flight provides built-in support for HTTP level caching. If the caching condition
is met, Flight will return an HTTP `304 Not Modified` response. The next time the
client requests the same resource, they will be prompted to use their locally
cached version.

## Last-Modified

You can use the `lastModified` method and pass in a UNIX timestamp to set the date
and time a page was last modified. The client will continue to use their cache until
the last modified value is changed.

```php
Flight::route('/news', function () {
  Flight::lastModified(1234567890);
  echo 'This content will be cached.';
});
```

## ETag

`ETag` caching is similar to `Last-Modified`, except you can specify any id you
want for the resource:

```php
Flight::route('/news', function () {
  Flight::etag('my-unique-id');
  echo 'This content will be cached.';
});
```

Keep in mind that calling either `lastModified` or `etag` will both set and check the
cache value. If the cache value is the same between requests, Flight will immediately
send an `HTTP 304` response and stop processing.

# Stopping

You can stop the framework at any point by calling the `halt` method:

```php
Flight::halt();
```

You can also specify an optional `HTTP` status code and message:

```php
Flight::halt(200, 'Be right back...');
```

Calling `halt` will discard any response content up to that point. If you want to stop
the framework and output the current response, use the `stop` method:

```php
Flight::stop();
```

# JSON

Flight provides support for sending JSON and JSONP responses. To send a JSON response you
pass some data to be JSON encoded:

```php
Flight::json(['id' => 123]);
```

For JSONP requests you, can optionally pass in the query parameter name you are
using to define your callback function:

```php
Flight::jsonp(['id' => 123], 'q');
```

So, when making a GET request using `?q=my_func`, you should receive the output:

```javascript
my_func({"id":123});
```

If you don't pass in a query parameter name it will default to `jsonp`.


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


# Framework Instance

Instead of running Flight as a global static class, you can optionally run it
as an object instance.

```php
require 'flight/autoload.php';

$app = new flight\Engine();

$app->route('/', function () {
  echo 'hello world!';
});

$app->start();
```

So instead of calling the static method, you would call the instance method with
the same name on the Engine object.

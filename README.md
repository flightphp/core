# Flight

Flight is a fast, simple, and extensible framework for PHP.
It allows you to quickly build RESTful web applications with minimal effort:

    require 'flight/Flight.php';

    Flight::route('/', function(){
        echo 'hello world!';
    });

    Flight::start();


## Installation

1\. Download and extract the Flight framework files to your web directory.

2\. Configure your webserver.

For **Apache**, edit your `.htaccess` file with the following:

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]

For **Nginx**, add the following to your server declaration:

    server {
        location / {
            try_files $uri $uri/ /index.php;
        }
    }

3\. Create your `index.php` file.

First include the framework.

    require 'flight/Flight.php';

Then define a route and assign a function to handle the request.

    Flight::route('/', function(){
        echo 'hello world!';
    });

Finally, start the framework.

    Flight::start();


## Routing

### The Basics

Routing in Flight is done by matching a URL pattern with a callback function.

    Flight::route('/', function(){
        echo 'hello world!';
    });

The callback can be any object that is callable. So you can use a regular function:

    function hello(){
        echo 'hello world!';
    }
    Flight::route('/', 'hello');

Or a class method:

    class Greeting {
        public static function hello() {
            echo 'hello world!';
        }
    }
    Flight::route('/', array('Greeting','hello'));

Routes are matched in the order they are defined. The first route to match a request is invoked.

### Method Routing

By default, route patterns are matched against all request methods. You can respond to specific
methods by placing an identifier before the URL.

    Flight::route('GET /', function(){
        echo 'I received a GET request.';
    });
    Flight::route('POST /', function(){
        echo 'I received a POST request.';
    });

You can also map multiple methods to a single callback by using a `|` delimiter:

    Flight::route('GET|POST /', function(){
        echo 'I received either a GET or a POST request.';
    });

Method specific routes have precedence over global routes.

### Regular Expressions

You can use regular expressions in your routes:

    Flight::route('/user/[0-9]+', function(){
        // This will match /user/1234
    });

You can also use the wildcard character `*` for matching:

    Flight::route('/blog/*', function(){
        // This will match /blog/2000/02/01
    });

### Named Parameters

You can specify named parameters in routes which will be passed along to your callback function.

    Flight::route('/@name/@id', function($name, $id){
        echo "hello, {$name} - {$id}!";
    });

You can also include regular expressions with your named parameters by using the `:` delimiter:

    Flight::route('/@name/@id:[0-9]{3}', function($name, $id){
        // This will match /bob/123
        // But will not match /bob/12345
    });

Note that named parameters only match URL segments. If you want to match multiple segments use the `*` wildcard.


## Extending

Flight is an extensible framework. Flight's features are individual components that can extended or overridden.
You can map your own methods, register your own classes, or even override existing classes and methods.

### Mapping Methods

To map your own custom method, you use the `map` function:

    // Map your method
    Flight::map('hello', function($name){
        echo "hello {$name}!";
    });

    // Call your custom method
    Flight::hello('Bob');

### Registering Classes

To register your own class, you use the `register` function:

    // Register your class
    Flight::register('user', 'User');

    // Get an instance of your class
    $user = Flight::user();

If the `User` class is not defined, Flight will look in it's local folder for a file called `User.php` and autoload it.
This is how Flight loads its default classes like Response, Request, and Router.

You can define the constructor parameters for your class by passing in an additional array:

    // Register class with constructor parameters
    Flight::register('db', 'Database', array('localhost','mydb','user','pass'));

    // Get an instance of your class
    // This will create an object with the defined parameters
    //
    //     new Database('localhost', 'test', 'user', 'pass');
    //
    $db = Flight::db();

You can also define a callback that will be executed immediately after class construction.

    // The callback will be passed the object that was constructed
    Flight::register('db', 'Database', array('localhost', 'mydb', 'user', 'pass'), function($db){
        $db->connect();
    });

By default, every time you load your class you will get a shared instance.
To get a new instance of a class, simply pass in *false* as a parameter:

    // Shared instance of User
    $shared = Flight::user();

    // New instance of User
    $new = Flight::user(false);

Keep in mind that mapped methods have precedence over registered classes. If you declare both
for the same method name, only the mapped method will be invoked.

## Overriding

Flight ships with lots of default functionality to help you get your started.
However, you can override these features to suit your needs without having to modify any code.

For example, when Flight cannot match a URL to a route, it invokes the `notFound` method which sends a generic HTTP 404 response.
You can override this method to handle 404 errors however you like by mapping over it:

    Flight::map('notFound', function(){
        // Display custom 404 page
        include 'errors/404.html';
    });

Flight also has custom error handling which you can override:

    Flight::map('error', function($e){
        // Log error somewhere
        log_error($e);
    });

Flight also allows you to replace core components if you want.
For example you can replace the default Router class with your own custom class:

    // Register your custom class
    Flight::register('router', 'MyRouter');

    // When Flight loads the Router instance, it will load your class
    $myrouter = Flight::router();

You can replace any of the default Flight components:

    Flight::request();
    Flight::response();
    Flight::router();
    Flight::view();

However, core framework methods like `map` and `register` cannot be overridden.


## Method Filtering

Flight allows you to filter methods before and after they are called. There are no predefined hooks
you need to memorize. You can simply filter any method Flight invokes, including custom methods that
you've mapped.

You can have a filter run before a method by doing:

    Flight::before('start', function(&$params){
        // Do something
    });

You can have a filter run after a method by doing:

    Flight::after('start', function(&$output){
        // Do something
    });

You can add as many filters as you want to any method. They will be called in the order
that they are declared.

Notice that the filter callbacks have arguments passed to them. All *before* filters
are passed an array of the method parameters. All *after* filters are passed the output of
the method being filtered. The arguments are passed by reference so your filter simply needs
to modify the contents.

Here's an example the filtering process:

    // Map a custom method
    Flight::map('hello', function($name){
        return "Hello, {$name}!";
    });

    // Add a before filter
    Flight::before('hello', function(&$params){
        // Manipulate the parameter
        $params[0] = strtoupper($params[0]);
    });

    // Add an after filter
    Flight::after('hello', function(&$output){
        // Manipulate the output
        $output .= " Have a nice day!";
    }

    // Invoke the custom method
    echo Flight::hello('bob');

This should display:

    Hello BOB! Have a nice day! 

### Framework Methods

You can apply filters to any of these existing framework methods:

    start
    stop
    route
    halt
    error
    notFound
    redirect
    render
    etag
    lastModified

Core framework methods like `map` and `register` however cannot be filtered because they are called
directly and not invoked dynamically.


## Variables

Flight allows you to save variables so that they can be used anywhere in your application.

    // Save your variable
    Flight::set('id', 123);

    // Elsewhere in your application
    $id = Flight::get('id');

To see if a variable has been set you can do:

    if (Flight::exists('id')) {
        // Do something
    }

You can clear a variable by doing:

    // Clears the id variable
    Flight::clear('id');

    // Clears all variables
    Flight::clear();

Flight also uses variables for configuration purposes.

    Flight::set('flight.lib.path', '/path/to/library');


## Views

Flight provides you with some basic templating functionality. To display a view call the `render` method with the
name of the template file and optional template data:

    Flight::render('hello.php', array('name', 'Bob'));

The template data you pass in is automatically injected into the template and can be reference like a local variable.
Template files are simply PHP files. If the content of the `hello.php` template file is:

    Hello, <?php echo $name; ?>!

The output would be:

    Hello, Bob!

By default Flight will look for a `views` directory for template files. You can set an alternate path for your templates
by setting the following config:

    Flight::set('flight.views.path', '/path/to/views');

### Custom Views

Flight allows you to swap out the default view engine simply by registering your own view class.
Here's how you would use the Smarty template engine for your views:

    // Load Smarty library
    require './Smarty/libs/Smarty.class.php';

    // Register Smarty as the view class and pass
    // a callback function to configure Smarty on load
    Flight::register('view', 'Smarty', array(), function($smarty){
        $smarty->template_dir = './templates/';
        $smarty->compile_dir = './templates_c/';
        $smarty->config_dir = './config/';
        $smarty->cache_dir = './cache/';
    });

    // Assign template data
    Flight::view()->assign('name', 'Bob');

    // Display the template
    Flight::view()->display('hello.tpl');

For completeness, you should also override Flight's default render method:

    Flight::map('render', function($template, $data){
        Flight::view()->assign($data);
        Flight::view()->display($template);
    });


## Error Handling

### Errors and Exceptions

All errors and exceptions are caught by Flight and passed to the `error` method.
The default behavior is to send an HTTP `500 Internal Server Error` response with some error information.
You can override this behavior for your own needs.

### Not Found

When a URL can't be found, Flight calls the `notFound` method. The default behavior is to
send an HTTP `404 Not Found` response with a simple message. You can override this behavior for your own needs.


## Redirects

You can redirect the current request by using the `redirect` method and passing in a new URL:

    Flight::redirect('/new/location');


## HTTP Caching

Flight provides built-in support for HTTP level caching. If the caching condition is met,
Flight will return an HTTP `304 Not Modified` response. The next time the client requests the same resource,
they will be prompted to use their locally cached version.

### Last-Modified

You can use the `lastModified` method and pass in a UNIX timestamp to set the date and time a page was last modified.
The client will continue to use their cache until the last modified value is changed.

    Flight::route('/news', function(){
        Flight::lastModified(1234567890);
        echo 'This content will be cached.';
    });

### ETag

ETag caching is similar to Last-Modified, except you can specify any id you want for the resource:

    Flight::route('/news', function(){
        Flight::etag('my-unique-id');
        echo 'This content will be cached.';
    });

Keep in mind that calling either `lastModified` or `etag` will both set and check the cache value.
If the cache value is the same between requests, Flight will immediately send the `304` response and stop
processing.


## Stopping

You can stop the framework at any point by calling the `halt` method:

    Flight::halt();

You can also specify an optional HTTP status code and message:

    Flight::halt(200, 'Be right back...');

Calling `halt` will discard any response content up to that point.
If you want to stop the framework and output the current response, use the `stop` method:

    Flight::stop();


## Requirements

Flight requires PHP 5.3 or later.


## License

Flight is licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) license.

# Flight

Flight is an extensible micro-framework for PHP.
It allows you to quickly build RESTful web applications with minimal effort:

    require 'flight/Flight.php';

    Flight::route('/', function(){
        echo 'hello world!';
    });

    Flight::start();

## Installation

1. Download and extract the Flight framework files to your web directory.

2. Configure your webserver:

For **Apache**, edit your _.htaccess_ file with the following:

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]

For **Nginx**, add the following to your _server_ declaration:

    server {
        location / {
            try_files $uri $uri/ /index.php;
        }
    }

3. Create your _index.php_ file.

First include the framework.

    require 'flight/Flight.php';

Then define a route and assign a function to handle the request.

    Flight::route('/', function(){
        echo 'hello world!';
    });

Finally, start the framework.

    Flight::start();

## Requirements

Flight requires PHP 5.3 or later.

## License

Flight licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) license.

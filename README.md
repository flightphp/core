# Flight

Flight is an extensible micro-framework for PHP.

## Example

    include 'flight/Flight.php';

    Flight::route('/', function(){
        echo 'hello world!';
    });

    Flight::start();

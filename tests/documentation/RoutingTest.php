<?php

use Dice\Dice;
use flight\core\Dispatcher;
use flight\database\PdoWrapper;
use flight\net\Route;
use PHPUnit\Framework\TestCase;

class Greeting
{
    public static function hello(): void
    {
        echo 'Hello, World from static method!';
    }
}

class Greeting2
{
    private string $name;

    public function __construct()
    {
        $this->name = 'John Doe';
    }

    public function hello(): void
    {
        echo "Hello, {$this->name}!";
    }
}

class Greeting3
{
    private PdoWrapper $pdo;

    public function __construct(PdoWrapper $pdo)
    {
        $this->pdo = $pdo;

        $this->pdo->query(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(20) NOT NULL UNIQUE
        )
        SQL);

        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec("INSERT INTO users (id, name) VALUES (1, 'John'), (2, 'Mike')");
    }

    public function hello(int $id): void
    {
        $name = $this->pdo->fetchField("SELECT name FROM users WHERE id = ?", [$id]);

        echo "Hello, world! My name is $name!";
    }
}

class RoutingTest extends TestCase
{
    protected function setUp(): void
    {
        Flight::init();
    }

    protected function tearDown(): void
    {
        Flight::clear();
        Flight::registerContainerHandler(Dispatcher::class);
    }

    public function testRouting(): void
    {
        Flight::route('/', function (): void {
            echo 'Hello, World from callable!';
        });

        Flight::route('/', function (): void {
            echo 'Hello, World from callable!!';
        });

        $this->expectOutputString('Hello, World from callable!');

        Flight::start();
    }

    public function testCallbacksAndFunctions(): void
    {
        function hello(): void
        {
            echo 'Hello, World from function!';
        }

        Flight::route('/', 'hello');

        $this->expectOutputString('Hello, World from function!');

        Flight::start();
    }

    public function testStaticMethods(): void
    {
        Flight::route('/', ['Greeting', 'hello']);

        $this->expectOutputString('Hello, World from static method!');

        Flight::start();
    }

    public function testInstanceMethod(): void
    {
        $greeting = new Greeting2();

        Flight::route('/', [$greeting, 'hello']);

        $this->expectOutputString('Hello, John Doe!');

        Flight::start();
    }

    public function testInstanceMethodLikeStaticMethod(): void
    {
        Flight::route('/', ['Greeting2', 'hello']);

        $this->expectOutputString('Hello, John Doe!');

        Flight::start();
    }

    public function testDependencyInjection(): void
    {
        // Setup the container with whatever params you need
        // See the Dependency Injection page for more information on PSR-11
        $dice = new Dice();

        // Don't forget to reassign the variable with '$dice = '!!!!!
        $dice = $dice->addRule(PdoWrapper::class, [
            'shared' => true,
            'constructParams' => ['sqlite::memory:']
        ]);

        // Register the container handler
        Flight::registerContainerHandler(function (string $class, array $params) use ($dice) {
            return $dice->create($class, $params);
        });

        $cases = [
            // Routes like normal
            ['Greeting3', 'hello'],

            // or
            'Greeting3->hello',

            // or
            'Greeting3::hello'
        ];

        foreach ($cases as $case) {
            Flight::route('/hello/@id', $case);
            Flight::request()->url = '/hello/1';

            $this->expectOutputString('Hello, world! My name is John!');

            Flight::start();
        }
    }

    /** @dataProvider methodsDataProvider */
    public function testMethodRouting(string $method): void
    {
        Flight::route("$method /", function () use ($method): void {
            echo "I received a $method request";
        });

        Flight::request()->method = $method;

        $this->expectOutputString("I received a $method request");

        Flight::start();
    }

    /** @dataProvider methodsDataProvider */
    public function testMethodsRoutingShorthands(string $method): void
    {
        if ($method === 'GET') {
            $this->assertTrue(true);

            return;
        }

        $lowerCaseMethod = strtolower($method);

        Flight::{$lowerCaseMethod}('/', function () use ($method): void {
            echo "I received a $method request.";
        });

        Flight::request()->method = $method;

        $this->expectOutputString("I received a $method request.");

        Flight::start();
    }

    /** @dataProvider methodsDataProvider */
    public function testMultipleMethodsRouting(string $method): void
    {
        $methods = join('|', array_map(function (array $caseParams): string {
            return $caseParams[0];
        }, self::methodsDataProvider()));

        Flight::route("$methods /", function () use ($method): void {
            echo "I received a $method request.";
        });

        Flight::request()->method = $method;

        $this->expectOutputString("I received a $method request.");

        Flight::start();
    }

    /** @dataProvider methodsDataProvider */
    public function testRouterObject(string $method): void {
        $router = Flight::router();

        $router->map('/', function () use ($method): void {
            echo "I received a $method request.";
        });

        Flight::request()->method = $method;

        $this->expectOutputString("I received a $method request.");

        Flight::start();
    }

    /** @dataProvider methodsDataProvider */
    public function testRouterObjectWithShorthands(string $method): void
    {
        if ($method === 'GET') {
            $this->assertTrue(true);

            return;
        }

        $lowerCaseMethod = strtolower($method);
        $router = Flight::router();

        $router->{$lowerCaseMethod}('/', function () use ($method): void {
            echo "I received a $method request.";
        });

        Flight::request()->method = $method;

        $this->expectOutputString("I received a $method request.");

        Flight::start();
    }

    public static function methodsDataProvider(): array
    {
        return [['GET'], ['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }
}

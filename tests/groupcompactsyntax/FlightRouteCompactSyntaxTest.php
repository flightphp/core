<?php

use PHPUnit\Framework\TestCase;
use tests\groupcompactsyntax\PostsController;
use tests\groupcompactsyntax\TodosController;
use tests\groupcompactsyntax\UsersController;

require_once __DIR__ . '/UsersController.php';
require_once __DIR__ . '/PostsController.php';

final class FlightRouteCompactSyntaxTest extends TestCase
{
    public function setUp(): void
    {
        Flight::router()->clear();
    }

    public function testCanMapMethodsWithVerboseSyntax(): void
    {
        Flight::route('GET /users', [UsersController::class, 'list']);
        Flight::route('POST /users', [UsersController::class, 'handleRegister']);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(2, $routes);

        $this->assertSame('/users', $routes[0]->pattern);
        $this->assertSame([UsersController::class, 'list'], $routes[0]->callback);
        $this->assertSame('GET', $routes[0]->methods[0]);

        $this->assertSame('/users', $routes[1]->pattern);
        $this->assertSame([UsersController::class, 'handleRegister'], $routes[1]->callback);
        $this->assertSame('POST', $routes[1]->methods[0]);
    }

    public function testCanMapSomeMethods(): void
    {
        Flight::resource('/users', UsersController::class, [
            'GET /' => 'list',
            'POST /' => 'handleRegister'
        ]);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(2, $routes);

        $this->assertSame('/users/', $routes[0]->pattern);
        $this->assertSame('GET', $routes[0]->methods[0]);
        $this->assertSame([UsersController::class, 'list'], $routes[0]->callback);

        $this->assertSame('/users/', $routes[1]->pattern);
        $this->assertSame('POST', $routes[1]->methods[0]);
        $this->assertSame([UsersController::class, 'handleRegister'], $routes[1]->callback);
    }

    public function testCanMapDefaultMethods(): void
    {
        Flight::resource('/posts', PostsController::class);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(7, $routes);

        $this->assertSame('/posts/', $routes[0]->pattern);
        $this->assertSame('GET', $routes[0]->methods[0]);
        $this->assertSame([PostsController::class, 'index'], $routes[0]->callback);

        $this->assertSame('/posts/@id/', $routes[1]->pattern);
        $this->assertSame('GET', $routes[1]->methods[0]);
        $this->assertSame([PostsController::class, 'show'], $routes[1]->callback);

        $this->assertSame('/posts/create/', $routes[2]->pattern);
        $this->assertSame('GET', $routes[2]->methods[0]);
        $this->assertSame([PostsController::class, 'create'], $routes[2]->callback);

        $this->assertSame('/posts/', $routes[3]->pattern);
        $this->assertSame('POST', $routes[3]->methods[0]);
        $this->assertSame([PostsController::class, 'store'], $routes[3]->callback);

        $this->assertSame('/posts/@id/edit/', $routes[4]->pattern);
        $this->assertSame('GET', $routes[4]->methods[0]);
        $this->assertSame([PostsController::class, 'edit'], $routes[4]->callback);

        $this->assertSame('/posts/@id/', $routes[5]->pattern);
        $this->assertSame('PUT', $routes[5]->methods[0]);
        $this->assertSame([PostsController::class, 'update'], $routes[5]->callback);

        $this->assertSame('/posts/@id/', $routes[6]->pattern);
        $this->assertSame('DELETE', $routes[6]->methods[0]);
        $this->assertSame([PostsController::class, 'destroy'], $routes[6]->callback);
    }

    public function testCanMapExistingMethods(): void
    {
        Flight::resource('/todos', TodosController::class);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(2, $routes);
    }
}

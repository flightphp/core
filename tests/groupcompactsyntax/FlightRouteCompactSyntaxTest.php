<?php

declare(strict_types=1);

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
        Flight::route('GET /users', [UsersController::class, 'index']);
        Flight::route('DELETE /users/@id', [UsersController::class, 'destroy']);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(2, $routes);

        $this->assertSame('/users', $routes[0]->pattern);
        $this->assertSame([UsersController::class, 'index'], $routes[0]->callback);
        $this->assertSame('GET', $routes[0]->methods[0]);

        $this->assertSame('/users/@id', $routes[1]->pattern);
        $this->assertSame([UsersController::class, 'destroy'], $routes[1]->callback);
        $this->assertSame('DELETE', $routes[1]->methods[0]);
    }

    public function testOptionsOnly(): void
    {
        Flight::resource('/users', UsersController::class, [
            'only' => [ 'index', 'destroy' ]
        ]);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(2, $routes);

        $this->assertSame('/users', $routes[0]->pattern);
        $this->assertSame('GET', $routes[0]->methods[0]);
        $this->assertSame([UsersController::class, 'index'], $routes[0]->callback);

        $this->assertSame('/users/@id', $routes[1]->pattern);
        $this->assertSame('DELETE', $routes[1]->methods[0]);
        $this->assertSame([UsersController::class, 'destroy'], $routes[1]->callback);
    }

    public function testDefaultMethods(): void
    {
        Flight::resource('/posts', PostsController::class);

        $routes = Flight::router()->getRoutes();
        $this->assertCount(7, $routes);

        $this->assertSame('/posts', $routes[0]->pattern);
        $this->assertSame('GET', $routes[0]->methods[0]);
        $this->assertSame([PostsController::class, 'index'], $routes[0]->callback);
        $this->assertSame('posts.index', $routes[0]->alias);

        $this->assertSame('/posts/create', $routes[1]->pattern);
        $this->assertSame('GET', $routes[1]->methods[0]);
        $this->assertSame([PostsController::class, 'create'], $routes[1]->callback);
        $this->assertSame('posts.create', $routes[1]->alias);

        $this->assertSame('/posts', $routes[2]->pattern);
        $this->assertSame('POST', $routes[2]->methods[0]);
        $this->assertSame([PostsController::class, 'store'], $routes[2]->callback);
        $this->assertSame('posts.store', $routes[2]->alias);

        $this->assertSame('/posts/@id', $routes[3]->pattern);
        $this->assertSame('GET', $routes[3]->methods[0]);
        $this->assertSame([PostsController::class, 'show'], $routes[3]->callback);
        $this->assertSame('posts.show', $routes[3]->alias);

        $this->assertSame('/posts/@id/edit', $routes[4]->pattern);
        $this->assertSame('GET', $routes[4]->methods[0]);
        $this->assertSame([PostsController::class, 'edit'], $routes[4]->callback);
        $this->assertSame('posts.edit', $routes[4]->alias);

        $this->assertSame('/posts/@id', $routes[5]->pattern);
        $this->assertSame('PUT', $routes[5]->methods[0]);
        $this->assertSame([PostsController::class, 'update'], $routes[5]->callback);
        $this->assertSame('posts.update', $routes[5]->alias);

        $this->assertSame('/posts/@id', $routes[6]->pattern);
        $this->assertSame('DELETE', $routes[6]->methods[0]);
        $this->assertSame([PostsController::class, 'destroy'], $routes[6]->callback);
        $this->assertSame('posts.destroy', $routes[6]->alias);
    }

    public function testOptionsExcept(): void
    {
        Flight::resource('/todos', TodosController::class, [
            'except' => [ 'create', 'store', 'update', 'destroy', 'edit' ]
        ]);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(2, $routes);

        $this->assertSame('/todos', $routes[0]->pattern);
        $this->assertSame('GET', $routes[0]->methods[0]);
        $this->assertSame([TodosController::class, 'index'], $routes[0]->callback);

        $this->assertSame('/todos/@id', $routes[1]->pattern);
        $this->assertSame('GET', $routes[1]->methods[0]);
        $this->assertSame([TodosController::class, 'show'], $routes[1]->callback);
    }

    public function testOptionsMiddlewareAndAliasBase(): void
    {
        Flight::resource('/todos', TodosController::class, [
            'middleware' => [ 'auth' ],
            'alias_base' => 'nothanks'
        ]);

        $routes = Flight::router()->getRoutes();

        $this->assertCount(7, $routes);

        $this->assertSame('/todos', $routes[0]->pattern);
        $this->assertSame('GET', $routes[0]->methods[0]);
        $this->assertSame([TodosController::class, 'index'], $routes[0]->callback);
        $this->assertSame('auth', $routes[0]->middleware[0]);
        $this->assertSame('nothanks.index', $routes[0]->alias);

        $this->assertSame('/todos/create', $routes[1]->pattern);
        $this->assertSame('GET', $routes[1]->methods[0]);
        $this->assertSame([TodosController::class, 'create'], $routes[1]->callback);
        $this->assertSame('auth', $routes[1]->middleware[0]);
        $this->assertSame('nothanks.create', $routes[1]->alias);
    }
}

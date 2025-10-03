<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

// Route to list all available test routes
Flight::route('GET /', function () {
    echo "<h2>Available Test Routes:</h2><ul>";
    echo "<li><a href='/route0'>/route0</a> (static)</li>";
    echo "<li><a href='/user1/123'>/user1/123</a> (single param)</li>";
    echo "<li><a href='/post2/tech/my-article'>/post2/tech/my-article</a> (multiple params)</li>";
    echo "<li><a href='/api3/456'>/api3/456</a> (regex constraint)</li>";
    echo "<li>/submit4/document (POST only)</li>";
    echo "<li><a href='/admin5/dashboard'>/admin5/dashboard</a> (grouped)</li>";
    echo "<li><a href='/admin5/users/789'>/admin5/users/789</a> (grouped with regex)</li>";
    echo "<li><a href='/file6/path/to/document.pdf'>/file6/path/to/document.pdf</a> (complex regex)</li>";
    echo "<li><a href='/resource7/999'>/resource7/999</a> (multi-method)</li>";
    echo "</ul>";
    echo "<h3>Performance Test URLs:</h3>";
    echo "<p>Static routes: /route0, /route8, /route16, /route24, /route32, /route40, /route48</p>";
    echo "<p>Param routes: /user1/123, /user9/456, /user17/789</p>";
    echo "<p>Complex routes: /post2/tech/article, /api3/123, /file6/test.txt</p>";
});


for ($i = 0; $i < 50; $i++) {
    $route_type = $i % 8;

    switch ($route_type) {
        case 0:
            // Simple static routes
            Flight::route("GET /route{$i}", function () use ($i) {
                echo "This is static route {$i}";
            });
            break;

        case 1:
            // Routes with single parameter
            Flight::route("GET /user{$i}/@id", function ($id) use ($i) {
                echo "User route {$i} with ID: {$id}";
            });
            break;

        case 2:
            // Routes with multiple parameters
            Flight::route("GET /post{$i}/@category/@slug", function ($category, $slug) use ($i) {
                echo "Post route {$i}: {$category}/{$slug}";
            });
            break;

        case 3:
            // Routes with regex constraints
            Flight::route("GET /api{$i}/@id:[0-9]+", function ($id) use ($i) {
                echo "API route {$i} with numeric ID: {$id}";
            });
            break;

        case 4:
            // POST routes with parameters
            Flight::route("POST /submit{$i}/@type", function ($type) use ($i) {
                echo "POST route {$i} with type: {$type}";
            });
            break;

        case 5:
            // Grouped routes
            Flight::group("/admin{$i}", function () use ($i) {
                Flight::route("GET /dashboard", function () use ($i) {
                    echo "Admin dashboard {$i}";
                });
                Flight::route("GET /users/@id:[0-9]+", function ($id) use ($i) {
                    echo "Admin user {$i}: {$id}";
                });
            });
            break;

        case 6:
            // Complex regex patterns
            Flight::route("GET /file{$i}/@path:.*", function ($path) use ($i) {
                echo "File route {$i} with path: {$path}";
            });
            break;

        case 7:
            // Multiple HTTP methods
            Flight::route("GET|POST|PUT /resource{$i}/@id", function ($id) use ($i) {
                echo "Multi-method route {$i} for resource: {$id}";
            });
            break;
    }
}
// Add some predictable routes for easy performance testing
Flight::route('GET /test-static', function () {
    $memory_start = memory_get_usage();
    $memory_peak = memory_get_peak_usage();
    echo "Static test route";
    if (isset($_GET['memory'])) {
        echo "\nMemory: " . round($memory_peak / 1024, 2) . " KB";
    }
});

Flight::route('GET /test-param/@id', function ($id) {
    $memory_start = memory_get_usage();
    $memory_peak = memory_get_peak_usage();
    echo "Param test route: {$id}";
    if (isset($_GET['memory'])) {
        echo "\nMemory: " . round($memory_peak / 1024, 2) . " KB";
    }
});

Flight::route('GET /test-complex/@category/@slug', function ($category, $slug) {
    $memory_start = memory_get_usage();
    $memory_peak = memory_get_peak_usage();
    echo "Complex test route: {$category}/{$slug}";
    if (isset($_GET['memory'])) {
        echo "\nMemory: " . round($memory_peak / 1024, 2) . " KB";
    }
});
Flight::start();

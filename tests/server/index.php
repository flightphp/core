<?php

declare(strict_types=1);

/*
 * This is the test file where we can open up a quick test server and make
 * sure that the UI is really working the way we would expect it to.
 *
 * @author Kristaps Muižnieks https://github.com/krmu
 */

require_once file_exists(__DIR__ . '/../../vendor/autoload.php')
    ? __DIR__ . '/../../vendor/autoload.php'
    : __DIR__ . '/../../flight/autoload.php';

Flight::set('flight.content_length', false);
Flight::set('flight.views.path', './');
Flight::set('flight.views.extension', '.phtml');
//Flight::set('flight.v2.output_buffering', true);

require_once 'LayoutMiddleware.php';

Flight::group('', function () {

    // Test 1: Root route
    Flight::route('/', function () {
        echo '<span id="infotext">Route text:</span> Root route works!';
    });
    Flight::route('/querytestpath', function () {
        echo '<span id="infotext">Route text:</span> This ir query route<br>';
        echo "I got such query parameters:<pre>";
        print_r(Flight::request()->query);
        echo "</pre>";
    }, false, "querytestpath");

    // Test 2: Simple route
    Flight::route('/test', function () {
        echo '<span id="infotext">Route text:</span> Test route works!';
    });

    // Test 3: Route with parameter
    Flight::route('/user/@name', function ($name) {
        echo "<span id='infotext'>Route text:</span> Hello, $name!";
    });
    Flight::route('POST /postpage', function () {
        echo '<span id="infotext">Route text:</span> THIS IS POST METHOD PAGE';
    }, false, "postpage");

    // Test 4: Grouped routes
    Flight::group('/group', function () {
        Flight::route('/test', function () {
            echo '<span id="infotext">Route text:</span> Group test route works!';
        });
        Flight::route('/user/@name', function ($name) {
            echo "<span id='infotext'>Route text:</span> There is variable called name and it is $name";
        });
        Flight::group('/group1', function () {
            Flight::group('/group2', function () {
                Flight::group('/group3', function () {
                    Flight::group('/group4', function () {
                        Flight::group('/group5', function () {
                            Flight::group('/group6', function () {
                                Flight::group('/group7', function () {
                                    Flight::group('/group8', function () {
                                        Flight::route('/final_group', function () {
                                            echo 'Mega Group test route works!';
                                        }, false, "final_group");
                                    });
                                });
                            });
                        });
                    });
                });
            });
        });
    });

    // Test 5: Route alias
    Flight::route('/alias', function () {
        echo '<span id="infotext">Route text:</span> Alias route works!';
    }, false, 'aliasroute');

    /** Middleware test */
    include_once 'AuthCheck.php';
    $middle = new AuthCheck();
    // Test 6: Route with middleware
    Flight::route('/protected', function () {
        echo '<span id="infotext">Route text:</span> Protected route works!';
    })->addMiddleware([$middle]);

    // Test 7: Route with template
    Flight::route('/template/@name', function ($name) {
        Flight::render('template.phtml', ['name' => $name]);
    });

    // Test 8: Throw an error
    Flight::route('/error', function () {
        trigger_error('This is a successful error');
    });
}, [new LayoutMiddleware()]);

Flight::map('error', function (Throwable $e) {
    $styles = join(';', [
        'border: 2px solid red',
        'padding: 21px',
        'background: lightgray',
        'font-weight: bold'
    ]);

    echo sprintf(
        "<h1>500 Internal Server Error</h1><h3>%s (%s)</h3><pre style=\"$styles\">%s</pre>",
        $e->getMessage(),
        $e->getCode(),
        str_replace(getenv('PWD'), '***CONFIDENTIAL***', $e->getTraceAsString())
    );

    echo "<br><a href='/'>Go back</a>";
});
Flight::map('notFound', function () {
    echo '<span id="infotext">Route text:</span> The requested URL was not found<br>';
    echo "<a href='/'>Go back</a>";
});

Flight::start();

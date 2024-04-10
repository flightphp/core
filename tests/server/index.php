<?php

declare(strict_types=1);

use flight\core\Loader;
use flight\database\PdoWrapper;
use tests\classes\Container;
use tests\classes\ContainerDefault;

/*
 * This is the test file where we can open up a quick test server and make
 * sure that the UI is really working the way we would expect it to.
 *
 * @author Kristaps Muižnieks https://github.com/krmu
 */

 require file_exists(__DIR__ . '/../../vendor/autoload.php') ? __DIR__ . '/../../vendor/autoload.php' : __DIR__ . '/../../flight/autoload.php';

Flight::set('flight.content_length', false);
Flight::set('flight.views.path', './');
Flight::set('flight.views.extension', '.phtml');
Loader::setV2ClassLoading(false);
Flight::path(__DIR__);

Flight::group('', function () {

    // Test 1: Root route
    Flight::route('/', function () {
        echo '<span id="infotext">Route text:</span> Root route works!';
        if (Flight::request()->query->redirected) {
            echo '<br>Redirected from /redirect route successfully!';
        }
    });
    Flight::route('/querytestpath', function () {
        echo '<span id="infotext">Route text:</span> This is query route<br>';
        echo "Query parameters:<pre>";
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

    // Test 10: Halt
    Flight::route('/halt', function () {
        Flight::halt(400, 'Halt worked successfully');
    });

    // Test 11: Redirect
    Flight::route('/redirect', function () {
        Flight::redirect('/?redirected=1');
    });

    // Test 12: Redirect with status code
    Flight::route('/streamResponse', function () {
        echo "Streaming a response";
        for ($i = 1; $i <= 50; $i++) {
            echo ".";
            usleep(50000);
            ob_flush();
        }
        echo "is successful!!";
    })->streamWithHeaders(['Content-Type' => 'text/html', 'status' => 200 ]);
    // Test 14: Overwrite the body with a middleware
    Flight::route('/overwrite', function () {
        echo '<span id="infotext">Route text:</span> This route status is that it <span style="color:red; font-weight: bold;">failed</span>';
    })->addMiddleware([new OverwriteBodyMiddleware()]);

    // Test 15: UTF8 Chars in url
    Flight::route('/わたしはひとです', function () {
        echo '<span id="infotext">Route text:</span> This route status is that it <span style="color:green; font-weight: bold;">succeeded はい!!!</span>';
    });

    // Test 16: UTF8 Chars in url with utf8 params
    Flight::route('/わたしはひとです/@name', function ($name) {
        echo '<span id="infotext">Route text:</span> This route status is that it <span style="color:' . ($name === 'ええ' ? 'green' : 'red') . '; font-weight: bold;">' . ($name === 'ええ' ? 'succeeded' : 'failed') . ' URL Param: ' . $name . '</span>';
    });

    // Test 17: Slash in param
    Flight::route('/redirect/@id', function ($id) {
        echo '<span id="infotext">Route text:</span> This route status is that it <span style="color:' . ($id === 'before/after' ? 'green' : 'red') . '; font-weight: bold;">' . ($id === 'before/after' ? 'succeeded' : 'failed') . ' URL Param: ' . $id . '</span>';
    });

    Flight::set('test_me_out', 'You got it boss!'); // used in /no-container route
    Flight::route('/no-container', ContainerDefault::class . '->testUi');
    Flight::route('/dice', Container::class . '->testThePdoWrapper');
    Flight::route('/Pascal_Snake_Case', Pascal_Snake_Case::class . '->doILoad');
}, [ new LayoutMiddleware() ]);

// Test 9: JSON output (should not output any other html)
Flight::route('/json', function () {
    Flight::json(['message' => 'JSON renders successfully!']);
});

// Test 13: JSONP output (should not output any other html)
Flight::route('/jsonp', function () {
    Flight::jsonp(['message' => 'JSONP renders successfully!'], 'jsonp');
});

Flight::map('error', function (Throwable $e) {
    echo sprintf(
        <<<HTML
        <h1>500 Internal Server Error</h1>
            <h3>%s (%s)</h3>
            <pre style="border: 2px solid red; padding: 21px; background: lightgray; font-weight: bold;">%s</pre>
        HTML,
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

Flight::map('start', function () {

    if (Flight::request()->url === '/dice') {
        $dice = new \Dice\Dice();
        $dice = $dice->addRules([
            PdoWrapper::class => [
                'shared' => true,
                'constructParams' => [ 'sqlite::memory:' ]
            ]
        ]);
        Flight::registerContainerHandler(function ($class, $params) use ($dice) {
            return $dice->create($class, $params);
        });
    }

    // Default start behavior now
    Flight::_start();
});

Flight::start();

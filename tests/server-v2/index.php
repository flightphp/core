<?php

declare(strict_types=1);

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
// This enables the old functionality of Flight output buffering
Flight::set('flight.v2.output_buffering', true);

// Test 1: Root route
Flight::route('/', function () {
    echo '<span id="infotext">Route text:</span> Root route works!';
    if (Flight::request()->query->redirected) {
        echo '<br>Redirected from /redirect route successfully!';
    }
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
class AuthCheck
{
    public function before()
    {
        if (!isset($_COOKIE['user'])) {
            echo '<span id="infotext">Middleware text:</span> You are not authorized to access this route!';
        }
    }
}
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

// Test 9: JSON output (should not output any other html)
Flight::route('/json', function () {
    echo "\n\n\n\n\n";
    Flight::json(['message' => 'JSON renders successfully!']);
    echo "\n\n\n\n\n";
});

// Test 13: JSONP output (should not output any other html)
Flight::route('/jsonp', function () {
    echo "\n\n\n\n\n";
    Flight::jsonp(['message' => 'JSONP renders successfully!'], 'jsonp');
    echo "\n\n\n\n\n";
});

// Test 10: Halt
Flight::route('/halt', function () {
    Flight::halt(400, 'Halt worked successfully');
});

// Test 11: Redirect
Flight::route('/redirect', function () {
    Flight::redirect('/?redirected=1');
});

Flight::set('flight.views.path', './');
Flight::map('error', function (Throwable $error) {
    echo "<h1> An error occurred, mapped  error method worked, error below </h1>";
    echo '<pre style="border: 2px solid red; padding: 21px; background: lightgray; font-weight: bold;">';
    echo str_replace(getenv('PWD'), "***CLASSIFIED*****", $error->getTraceAsString());
    echo "</pre>";
    echo "<a href='/'>Go back</a>";
});
Flight::map('notFound', function () {
    echo '<span id="infotext">Route text:</span> The requested URL was not found';
    echo "<a href='/'>Go back</a>";
});
echo '
<style>
    ul {
        list-style-type: none;
        margin: 0;
        padding: 0;
        overflow: hidden;
        background-color: #333;
    }

    li {
        float: left;
    }
    #infotext {
        font-weight: bold;
        color: blueviolet;
        }
    li a {
        display: block;
        color: white;
        text-align: center;
        padding: 14px 16px;
        text-decoration: none;
    }

    li a:hover {
        background-color: #111;
    }
    #container {
        color: #333;
        font-size: 16px;
        line-height: 1.5;
        margin: 20px 0;
        padding: 10px;
        border: 1px solid #ddd;
        background-color: #f9f9f9;
    }
    #debugrequest {
        color: #333;
        font-size: 16px;
        line-height: 1.5;
        margin: 20px 0;
        padding: 10px;
        border: 1px solid #ddd;
        background-color: #f9f9f9;
    }
</style>
<ul>
<li><a href="/">Root Route</a></li>
<li><a href="/test">Test Route</a></li>
<li><a href="/user/John">User Route with Parameter (John)</a></li>
<li><a href="/group/test">Grouped Test Route</a></li>
<li><a href="/group/user/Jane">Grouped User Route with Parameter (Jane)</a></li>
<li><a href="/alias">Alias Route</a></li>
<li><a href="/protected">Protected path</a></li>
<li><a href="/template/templatevariable">Template path</a></li>
<li><a href="/querytestpath?test=1&variable2=uuid&variable3=tester">Query path</a></li>
<li><a href="/postpage">Post method test page - should be 404</a></li>
<li><a href="' . Flight::getUrl('final_group') . '">Mega group</a></li>
<li><a href="/error">Error</a></li>
<li><a href="/json">JSON</a></li>
<li><a href="/jsonp?jsonp=myjson">JSONP</a></li>
<li><a href="/halt">Halt</a></li>
<li><a href="/redirect">Redirect</a></li>
</ul>';
Flight::before('start', function ($params) {
    echo '<div id="container">';
});
Flight::after('start', function ($params) {
    echo '</div>';
    echo '<div id="debugrequest">';
    echo "Request information<pre>";
    print_r(Flight::request());
    echo "</pre>";
    echo "</div>";
});
Flight::start();

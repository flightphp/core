<?php

declare(strict_types=1);

class LayoutMiddleware
{
    /**
     * Before
     *
     * @return void
     */
    public function before()
    {
        $final_route = Flight::getUrl('final_group');
        echo <<<HTML
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
<li><a href="/postpage">404 Not Found</a></li>
<li><a href="{$final_route}">Mega group</a></li>
<li><a href="/error">Error</a></li>
<li><a href="/json">JSON</a></li>
<li><a href="/jsonp?jsonp=myjson">JSONP</a></li>
<li><a href="/halt">Halt</a></li>
<li><a href="/redirect">Redirect</a></li>
<li><a href="/streamResponse">Stream</a></li>
<li><a href="/overwrite">Overwrite Body</a></li>
<li><a href="/redirect/before%2Fafter">Slash in Param</a></li>
<li><a href="/わたしはひとです">UTF8 URL</a></li>
<li><a href="/わたしはひとです/ええ">UTF8 URL w/ Param</a></li>
<li><a href="/dice">Dice Container</a></li>
<li><a href="/no-container">No Container Registered</a></li>
<li><a href="/Pascal_Snake_Case">Pascal_Snake_Case</a></li>
</ul>
HTML;
        echo '<div id="container">';
    }

    public function after()
    {
        echo '</div>';
        echo '<div id="debugrequest">';
        echo "<h2>Request Information</h2><pre>";
        print_r(Flight::request());
        echo '<h3>Raw Request Information</h3>';
        print_r($_SERVER);
        echo "</pre><h2>Response Information</h2><pre>";
        print_r(Flight::response());
        echo "</pre>";
        echo "</div>";
    }
}

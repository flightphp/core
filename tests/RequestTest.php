<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2012, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

require_once 'PHPUnit/Autoload.php';
require_once __DIR__.'/../flight/net/Request.php';

class RequestTest extends PHPUnit_Framework_TestCase
{
    private $request;

    function setUp() {
        putenv('REQUEST_URI=/');
        putenv('REQUEST_METHOD=GET');
        putenv('HTTP_X_REQUESTED_WITH=XMLHttpRequest');
        putenv('REQUEST_URI=/');
        putenv('REMOTE_ADDR=8.8.8.8');
        putenv('HTTPS=on');
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '32.32.32.32';

        $this->request = new \flight\net\Request();
    }

    function testDefaults() {
        $this->assertEquals('/', $this->request->url);
        $this->assertEquals('', $this->request->base);
        $this->assertEquals('GET', $this->request->method);
        $this->assertEquals('', $this->request->referrer);
        $this->assertEquals(true, $this->request->ajax);
        $this->assertEquals('HTTP/1.1', $this->request->scheme);
        $this->assertEquals('', $this->request->type);
        $this->assertEquals(0, $this->request->length);
        $this->assertEquals(true, $this->request->secure);
        $this->assertEquals('', $this->request->accept);
    }

    function testIpAddress() {
        $this->assertEquals('8.8.8.8', $this->request->ip);
        $this->assertEquals('32.32.32.32', $this->request->proxy_ip);
    }

    function testSubdirectory() {
        putenv('SCRIPT_NAME=/subdir/index.php');

        $request = new \flight\net\Request();

        $this->assertEquals('/subdir', $request->base);
    }

    function testQueryParameters() {
        putenv('REQUEST_URI=/page?id=1&name=bob');

        $request = new \flight\net\Request();

        $this->assertEquals('/page?id=1&name=bob', $request->url);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals('bob', $request->query->name);
    }

    function testCollections() {
        putenv('REQUEST_URI=/page?id=1');

        $_GET['q'] = 1;
        $_POST['q'] = 1;
        $_COOKIE['q'] = 1;
        $_FILES['q'] = 1;

        $request = new \flight\net\Request();

        $this->assertEquals(1, $request->query->q);
        $this->assertEquals(1, $request->query->id);
        $this->assertEquals(1, $request->data->q);
        $this->assertEquals(1, $request->cookies->q);
        $this->assertEquals(1, $request->files->q);
    }
}

<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2013, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\Engine;

require_once 'vendor/autoload.php';
require_once __DIR__ . '/../flight/autoload.php';

class RedirectTest extends PHPUnit\Framework\TestCase
{
    private Engine $app;

    protected function setUp(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/subdir/index.php';

        $this->app = new Engine();
        $this->app->set('flight.base_url', '/testdir');
    }

    public function getBaseUrl($base, $url)
    {
        if ('/' !== $base && false === strpos($url, '://')) {
            $url = preg_replace('#/+#', '/', $base . '/' . $url);
        }

        return $url;
    }

    // The base should be the subdirectory
    public function testBase()
    {
        $base = $this->app->request()->base;

        self::assertEquals('/subdir', $base);
    }

    // Absolute URLs should include the base
    public function testAbsoluteUrl()
    {
        $url = '/login';
        $base = $this->app->request()->base;

        self::assertEquals('/subdir/login', $this->getBaseUrl($base, $url));
    }

    // Relative URLs should include the base
    public function testRelativeUrl()
    {
        $url = 'login';
        $base = $this->app->request()->base;

        self::assertEquals('/subdir/login', $this->getBaseUrl($base, $url));
    }

    // External URLs should ignore the base
    public function testHttpUrl()
    {
        $url = 'http://www.yahoo.com';
        $base = $this->app->request()->base;

        self::assertEquals('http://www.yahoo.com', $this->getBaseUrl($base, $url));
    }

    // Configuration should override derived value
    public function testBaseOverride()
    {
        $url = 'login';
        if (null !== $this->app->get('flight.base_url')) {
            $base = $this->app->get('flight.base_url');
        } else {
            $base = $this->app->request()->base;
        }

        self::assertEquals('/testdir/login', $this->getBaseUrl($base, $url));
    }
}

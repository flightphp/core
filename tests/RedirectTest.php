<?php

declare(strict_types=1);

namespace tests;

use flight\Engine;
use PHPUnit\Framework\TestCase;

class RedirectTest extends TestCase
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
        if ($base !== '/' && strpos($url, '://') === false) {
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
        $base = $this->app->get('flight.base_url') ?? $this->app->request()->base;

        self::assertEquals('/testdir/login', $this->getBaseUrl($base, $url));
    }
}

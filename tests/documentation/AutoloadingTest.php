<?php

use app\utils\ArrayHelperUtil;
use PHPUnit\Framework\TestCase;

class AutoloadingTest extends TestCase
{
    public function testBasicExample(): void
    {
        require __DIR__ . '/autoloading-example/public/index.php';

        $this->assertTrue(class_exists('MyController'));
        $this->expectOutputString('Doing something');

        (new MyController)->index();
    }

    public function testNamespaces(): void {
        Flight::path(__DIR__ . '/autoloading-example');

        $this->assertTrue(class_exists('app\utils\ArrayHelperUtil'));
        $this->assertTrue(class_exists('app\controllers\MyController'));
        $this->expectOutputString('Doing something');

        (new ArrayHelperUtil)->changeArrayCase([]);
    }
}

<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

/**
 * The Flight class is a static representation of the framework.
 */
class Flight {
    /**
     * Framework engine.
     *
     * @var object
     */
    private static $engine;

    // Don't allow object instantiation
    private function __construct() {}
    private function __destruct() {}
    private function __clone() {}

    /**
     * Handles calls to static methods.
     *
     * @param string $name Method name
     * @param array $params Method parameters
     * @return mixed Callback results
     */
    public static function __callStatic($name, $params) {
        static $initialized = false;

        if (!$initialized) {
            require_once __DIR__.'/autoload.php';

            self::$engine = new \flight\Engine();

            $initialized = true;
        }

        return \flight\core\Dispatcher::invokeMethod(array(self::$engine, $name), $params);
    }
}


<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 */

/**
 * provides composer autoloader mechanism 
 */

include __DIR__.'/core/Loader.php';

$loader = new \flight\core\Loader();
$loader->start();

$loader->addDirectory(__DIR__);

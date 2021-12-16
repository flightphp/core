<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2013, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\core\Loader;

require_once __DIR__ . '/core/Loader.php';

Loader::autoload(true, [dirname(__DIR__)]);

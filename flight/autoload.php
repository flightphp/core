<?php

/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2013, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

declare(strict_types=1);

use flight\core\Loader;

require_once __DIR__ . '/core/Loader.php';

Loader::autoload(true, [dirname(__DIR__)]);

<?php

declare(strict_types=1);

use flight\core\Loader;

require_once __DIR__ . '/core/Loader.php';

Loader::autoload(true, [dirname(__DIR__)]);

<?php

declare(strict_types=1);

namespace tests\classes;

class Factory
{
    // Cannot be instantiated
    private function __construct()
    {
    }

    public static function create()
    {
        return new self();
    }
}

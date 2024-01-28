<?php

declare(strict_types=1);

namespace tests\classes;

class User
{
    public string $name;

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }
}

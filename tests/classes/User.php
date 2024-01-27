<?php

declare(strict_types=1);

class User
{
    public string $name;

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }
}

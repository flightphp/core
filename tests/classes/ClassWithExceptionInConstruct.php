<?php

declare(strict_types=1);

namespace tests\classes;

class ClassWithExceptionInConstruct
{
    public function __construct()
    {
        throw new \Exception('This is an exception in the constructor');
    }
}

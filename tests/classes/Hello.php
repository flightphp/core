<?php

declare(strict_types=1);

namespace tests\classes;

class Hello
{
    public function sayHi(): string
    {
        return 'hello';
    }

    public static function sayBye(): string
    {
        return 'goodbye';
    }
}

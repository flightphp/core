<?php

declare(strict_types=1);

namespace tests\classes;

use flight\util\Collection;

class Container
{
    protected Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function testTheContainer()
    {
        $this->collection->whatever = 'yay!';
        echo 'yay! I injected a collection, and it has ' . $this->collection->count() . ' items';
    }
}

<?php

declare(strict_types=1);

namespace tests\classes;

use flight\database\PdoWrapper;
use flight\util\Collection;

class Container
{
    protected Collection $collection;
    protected PdoWrapper $pdoWrapper;

    public function __construct(Collection $collection, PdoWrapper $pdoWrapper)
    {
        $this->collection = $collection;
        $this->pdoWrapper = $pdoWrapper;
    }

    public function testTheContainer()
    {
        $this->collection->whatever = 'yay!';
        echo 'yay! I injected a collection, and it has ' . $this->collection->count() . ' items';
    }

    public function testThePdoWrapper()
    {
        $value = intval($this->pdoWrapper->fetchField('SELECT 5'));
        echo 'Yay! I injected a PdoWrapper, and it returned the number ' . $value . ' from the database!';
    }
}

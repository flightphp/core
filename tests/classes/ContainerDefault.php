<?php

declare(strict_types=1);

namespace tests\classes;

use flight\Engine;

class ContainerDefault
{

    protected Engine $app;

    public function __construct(Engine $engine)
    {
        $engine->set('test_me_out', 'You got it boss!');
        $this->app = $engine;
    }

    public function testTheContainer()
    {
        return $this->app->get('test_me_out');
    }

}

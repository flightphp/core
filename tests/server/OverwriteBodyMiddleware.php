<?php

declare(strict_types=1);

namespace tests\server;

use Flight;

class OverwriteBodyMiddleware
{
    public function after(): void
    {
        $response = Flight::response();

        $response->write(str_replace(
            '<span style="color:red; font-weight: bold;">failed</span>',
            '<span style="color:green; font-weight: bold;">successfully works!</span>',
            $response->getBody()
        ), true);
    }
}

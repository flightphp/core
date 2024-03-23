<?php

declare(strict_types=1);

class OverwriteBodyMiddleware
{
    public function after()
    {
        $response = Flight::response();
        $response->write(str_replace('<span style="color:red; font-weight: bold;">failed</span>', '<span style="color:green; font-weight: bold;">successfully works!</span>', $response->getBody()), true);
    }
}

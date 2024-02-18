<?php

declare(strict_types=1);

class AuthCheck
{
    /**
     * Before
     *
     * @return void
     */
    public function before()
    {
        if (!isset($_COOKIE['user'])) {
            echo '<span id="infotext">Middleware text:</span> You are not authorized to access this route!';
        }
    }
}

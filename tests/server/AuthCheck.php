<?php

declare(strict_types=1);

class AuthCheck
{
    public function before(): void
    {
        if (!isset($_COOKIE['user'])) {
            echo '<span id="infotext">Middleware text:</span> You are not authorized to access this route!';
        }
    }
}

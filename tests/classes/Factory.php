<?php

class Factory
{
    // Cannot be instantiated
    private function __construct()
    {
    }

    public static function create()
    {
        return new self();
    }
}

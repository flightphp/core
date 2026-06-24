<?php

declare(strict_types=1);

namespace flight\template;

abstract class Component
{
    abstract public function html(): string;

    public function css(): string
    {
        return '';
    }

    public function js(): string
    {
        return '';
    }
}

<?php

declare(strict_types=1);

use flight\template\Component;

return new class($name, $occupation) extends Component
{
    private string $name;
    private string $occupation;

    public function __construct(string $name, string $occupation)
    {
        $this->name = $name;
        $this->occupation = $occupation;
    }

    public function html(): string
    {
        return "class-component-with-props: $this->occupation $this->name";
    }
};

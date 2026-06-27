<?php

declare(strict_types=1);

use flight\template\Component;

return new class extends Component
{
    #[Override]
    public function html(): string
    {
        return 'my-class-component';
    }
};
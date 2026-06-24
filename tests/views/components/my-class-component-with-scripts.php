<?php

declare(strict_types=1);

use flight\template\Component;

return new class extends Component
{
    #[Override]
    public function html(): string
    {
        return 'my-class-component-with-scripts';
    }

    #[Override]
    public function js(): string
    {
        return <<<'js'
        console.log('my-class-component-with-scripts')
        js;
    }
};
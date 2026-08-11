<?php

declare(strict_types=1);

use flight\template\Component;

return new class extends Component
{
    public function html(): string
    {
        return 'my-class-component-with-custom-script-tag';
    }

    public function js(): string
    {
        return <<<'js'
        <script type="module" data-component="my-class-component-with-custom-script-tag">
            console.log('my-class-component-with-custom-script-tag')
        </script>
        js;
    }
};

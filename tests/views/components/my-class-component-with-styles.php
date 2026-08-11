<?php

declare(strict_types=1);

use flight\template\Component;

return new class extends Component
{
    #[Override]
    public function html(): string
    {
        return <<<'html'
        <span class="my-class-component-with-styles">
            my-class-component-with-styles
        </span>
        html;
    }

    #[Override]
    public function css(): string
    {
        return <<<'css'
        .my-class-component-with-styles {
            color: red;
        }
        css;
    }
};
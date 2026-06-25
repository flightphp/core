<?php

declare(strict_types=1);

use flight\template\Component;

return new class extends Component
{
    public function html(): string
    {
        return <<<'html'
        <span class="my-class-component-with-custom-style-tag">
            my-class-component-with-custom-style-tag
        </span>
        html;
    }

    public function css(): string
    {
        return <<<'css'
        <style media="print" data-component="my-class-component-with-custom-style-tag">
            .my-class-component-with-custom-style-tag {
                color: purple;
            }
        </style>
        css;
    }
};

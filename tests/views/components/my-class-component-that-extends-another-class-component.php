<?php

declare(strict_types=1);

use tests\views\components\AnotherClassComponent;

require_once __DIR__ . '/another-class-component.php';

return new class extends AnotherClassComponent
{
    #[Override]
    public function html(): string
    {
        return parent::html() . ' extended by my-class-component-that-extends-another-class-component';
    }
};
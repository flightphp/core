<?php

declare(strict_types=1);

namespace tests\views\components;

use flight\template\Component;
use Override;

if (!class_exists('AnotherClassComponent')) {
    class AnotherClassComponent extends Component
    {
        #[Override]
        public function html(): string
        {
            return 'another-class-component';
        }
    }
}
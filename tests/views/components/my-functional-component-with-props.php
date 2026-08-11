<?php

declare(strict_types=1);

return fn (...$props): string => sprintf(
    'functional-component-with-props: %s %s',
    $props[1] ?? '',
    $props[0] ?? ''
);

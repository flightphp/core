<?php

declare(strict_types=1);

return fn (string $name, string $occupation): string =>
    "functional-component-with-individual-props: $occupation $name";

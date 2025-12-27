<?php

declare(strict_types=1);

namespace tests\classes;

use Ahc\Cli\IO\Interactor;

class NoExitInteractor extends Interactor
{
    public function error(string $text, bool $exit = false): self
    {
        $this->writer()->error($text, 0);
        return $this;
    }
    public function warn(string $text, bool $exit = false): self
    {
        $this->writer()->warn($text, 0);
        return $this;
    }
}

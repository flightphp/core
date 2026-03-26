<?php

declare(strict_types=1);

namespace tests\classes;

final class TesterClass
{
    public string $param1;
    public string $param2;
    public string $param3;
    public string $param4;
    public string $param5;
    public string $param6;

    public function __construct(
        string $param1,
        string $param2,
        string $param3,
        string $param4,
        string $param5,
        string $param6
    ) {
        $this->param1 = $param1;
        $this->param2 = $param2;
        $this->param3 = $param3;
        $this->param4 = $param4;
        $this->param5 = $param5;
        $this->param6 = $param6;
    }

    public function instanceMethod(): void
    {
        $this->param2 = $this->param1;
    }
}

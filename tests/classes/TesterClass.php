<?php

declare(strict_types=1);

namespace tests\classes;

class TesterClass
{
    public $param1;
    public $param2;
    public $param3;
    public $param4;
    public $param5;
    public $param6;

    public function __construct($param1, $param2, $param3, $param4, $param5, $param6)
    {
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

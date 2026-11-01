<?php

namespace Atwx\VectorSearch\ORM\Func\Distance;

use Atwx\VectorSearch\ORM\Func\Func;

abstract class VecDistance extends Func
{
    public function __construct(string | Func $v, string | Func $s)
    {
        parent::__construct($v, $s);
    }
}

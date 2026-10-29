<?php

namespace Atwx\ORM\Func\Generic\Distance;

use Atwx\ORM\Func\Generic\Func;
use Atwx\ORM\Func\Generic\Generic\Func as GenericFunc;

class VecDistance extends GenericFunc {
    public string $function = 'VEC_DISTANCE';

    // Eye-friendly wrapper
    public function __construct(string | Func $v, string | Func $s) {
        parent::__construct($v, $s);
    }
}

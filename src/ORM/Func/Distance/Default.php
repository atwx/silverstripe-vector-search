<?php

namespace Atwx\ORM\Func\Distance;

use Atwx\ORM\Func\Func;

class VecDistance extends Func {
    public string $function = 'VEC_DISTANCE';

    // Eye-friendly wrapper
    public function __construct(string | Func $v, string | Func $s) {
        parent::__construct($v, $s);
    }
}

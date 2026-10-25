<?php

namespace Atwx\ORM\Func\Distance;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;

class VecDistance {
    public $function = 'VEC_DISTANCE';

    // Eye-friendly wrapper
    public function __construct(string | Func $v, string | Func $s) {
        $parent::__construct($v, $s);
    }
}

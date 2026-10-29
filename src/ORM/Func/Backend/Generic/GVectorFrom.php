<?php

namespace Atwx\VectorSearch\ORM\Func\Generic;

use Atwx\VectorSearch\ORM\Func\VectorFrom;

class GVectorFrom extends VectorFrom {
    public string $function = 'VEC_FromText';
}

<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\Generic\Distance;

use Atwx\VectorSearch\ORM\Func\Distance\VecDistance;

class GVecDistance extends VecDistance
{
    public string $function = 'VEC_DISTANCE';
}

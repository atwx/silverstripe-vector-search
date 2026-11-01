<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\Generic\Distance;

use Atwx\VectorSearch\ORM\Func\Distance\VecDistanceCosine;

class GVecDistanceCosine extends VecDistanceCosine
{
    public string $function = 'VEC_DISTANCE_COSINE';
}

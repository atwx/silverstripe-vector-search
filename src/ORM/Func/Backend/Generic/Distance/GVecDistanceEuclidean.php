<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\Generic\Distance;

use Atwx\VectorSearch\ORM\Func\Distance\VecDistanceEuclidean;

class GVecDistanceEuclidean extends VecDistanceEuclidean {
    public string $function = 'VEC_DISTANCE_EUCLIDEAN';
}

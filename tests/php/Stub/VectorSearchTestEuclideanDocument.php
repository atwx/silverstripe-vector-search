<?php

namespace Atwx\VectorSearch\Tests\Stub;

use Atwx\VectorSearch\ORM\Func\SupportsVFuncs;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * The same column with a euclidean index, so that the distance stored on the index - and
 * therefore the metric VEC_DISTANCE() resolves to - can be told apart from
 * {@see VectorSearchTestDocument}'s cosine one.
 */
class VectorSearchTestEuclideanDocument extends DataObject implements TestOnly
{
    use SupportsVFuncs;

    private static string $table_name = 'VectorSearchTestEuclideanDocument';

    private static array $db = [
        'Content' => 'Text',
        'Embedding' => 'Vector("euclidean", 3)',
    ];
}

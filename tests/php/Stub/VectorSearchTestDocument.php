<?php

namespace Atwx\VectorSearch\Tests\Stub;

use Atwx\VectorSearch\ORM\Func\SupportsVFuncs;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A vector column with a cosine index on it, which is what VEC_DISTANCE() needs in order
 * to pick a metric.
 *
 * No $indexes entry: DBVector::getIndexSpecs() already asks for the vector index, and a
 * custom entry would replace it with a plain one.
 */
class VectorSearchTestDocument extends DataObject implements TestOnly
{
    use SupportsVFuncs;

    private static string $table_name = 'VectorSearchTestDocument';

    private static array $db = [
        'Content' => 'Text',
        // The first constructor argument is the distance, the second the size.
        'Embedding' => 'Vector("cosine", 3)',
    ];
}

<?php

namespace Atwx\VectorSearch\ORM\Helpers;

use Atwx\VectorSearch\ORM\Func\Column;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistance;
use Atwx\VectorSearch\ORM\Func\Func;
use Atwx\VectorSearch\ORM\Func\VectorFrom;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;

class VSearch
{
    public static function annotateQuery(mixed $query, string $field, Func $value): mixed
    {
        $compiled = $value->compile();
        $sql = array_shift($compiled);
        $params = $compiled;

        // selectField() takes SQL without placeholders, so the parameters are inlined here.
        $sqlWithParams = DB::inline_parameters($sql, $params);

        return $query->alterDataQuery(
            function (DataQuery $query) use ($field, $sqlWithParams) {
                $query->selectField(
                    $sqlWithParams,
                    $field
                );
            }
        );
    }

    public static function sortByDistance(
        mixed $query,
        string $column,
        string $text,
        string $dir = 'ASC',
        string $annotationName = 'embedding_distance'
    ): DataList {
        return VSearch::annotateQuery(
            $query,
            $annotationName,
            VecDistance::create(
                Column::create($column),
                VectorFrom::single($text)
            )
        )->sort($annotationName, $dir);
    }
}

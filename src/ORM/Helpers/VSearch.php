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
    public static function annotateQuery(mixed $query, string $field, Func $value)
    {
        $compiled = $value->compile();
        $sql = array_shift($compiled);
        $params = $compiled;

        // Note: Null is not supported
        $sql_with_params = DB::inline_parameters($sql, $params);

        return $query->alterDataQuery(
            function (DataQuery $query) use ($field, $sql_with_params) {
                $query->selectField(
                    $sql_with_params,
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
        string $annotation_name = 'embedding_distance'
    ): DataList {
        return VSearch::annotateQuery(
            $query,
            $annotation_name,
            VecDistance::create(
                Column::create($column),
                VectorFrom::single($text)
            )
        )->sort($annotation_name, $dir);
    }
}

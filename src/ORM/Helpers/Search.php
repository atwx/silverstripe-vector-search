<?php

namespace Atwx\ORM\Helpers;

use Atwx\ORM\Func\Column;
use Atwx\ORM\Func\Distance\VecDistanceCosine;
use Atwx\ORM\Func\Func;
use Atwx\ORM\Func\VectorFrom;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;

class VSearch {
    public static function annotate_query($query, string $field, Func $value) {
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

    public static function sort_by_distance(
        $query,
        string $column,
        string $text,
        string $dir = 'ASC',
        string $annotation_name = 'embedding_distance'
    ): DataList {
        return VSearch::annotate_query(
            $query,
            $annotation_name,
            new VecDistanceCosine(
                new Column('Embedding'),
                VectorFrom::single($query)
            )
        )->sort($annotation_name, $dir);
    }
}
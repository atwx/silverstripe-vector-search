<?php

namespace Atwx\ORM\Func;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;

function annotate_query($query, string $field, Func $value) {
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
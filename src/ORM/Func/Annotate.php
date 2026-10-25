<?php

namespace Atwx\ORM\Func;

use SilverStripe\ORM\DataQuery;

function annotate(DataQuery $query, string $field, Func | string $value) {
    return $query->alterDataQuery(
        function (DataQuery $query) use ($field, $value) {
            $query->selectField(
                Func::compile_obj($value),
                $field
            );
        }
    );
}
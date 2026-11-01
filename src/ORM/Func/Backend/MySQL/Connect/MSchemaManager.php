<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\MySQL\Connect;

use Override;
use SilverStripe\ORM\Connect\MySQLSchemaManager;

class MSchemaManager extends MySQLSchemaManager {
    /**
     * Modified version of MySQLSchemaManager's implementation. Maps the vector type.
     */
    #[Override]
    public function indexList($table)
    {
        $indexes = $this->query("SHOW INDEXES IN \"$table\"");
        $groupedIndexes = [];
        $indexList = [];

        foreach ($indexes as $index) {
            $column = $index['Column_name'];
            if (isset($index['Collation']) && $index['Collation'] !== 'NULL') {
                $column .= match ($index['Collation']) {
                    'A' => ' ASC',
                    'D' => ' DESC',
                };
            }
            $groupedIndexes[$index['Key_name']]['fields'][$index['Seq_in_index']] = $column;

            if ($index['Index_type'] == 'FULLTEXT') {
                $groupedIndexes[$index['Key_name']]['type'] = 'fulltext';
            } elseif (!$index['Non_unique']) {
                $groupedIndexes[$index['Key_name']]['type'] = 'unique';
            } elseif ($index['Index_type'] == 'HASH') {
                $groupedIndexes[$index['Key_name']]['type'] = 'hash';
            } elseif ($index['Index_type'] == 'RTREE') {
                $groupedIndexes[$index['Key_name']]['type'] = 'rtree';
            } elseif ($index['Index_type'] == 'VECTOR') {
                $groupedIndexes[$index['Key_name']]['type'] = 'vector';
            } else {
                $groupedIndexes[$index['Key_name']]['type'] = 'index';
            }

        }

        if ($groupedIndexes) {
            foreach ($groupedIndexes as $index => $details) {
                ksort($details['fields']);
                $indexList[$index] = [
                    'name' => $index,
                    'columns' => $details['fields'],
                    'type' => $details['type'],
                ];
            }
        }

        return $indexList;
    }
}

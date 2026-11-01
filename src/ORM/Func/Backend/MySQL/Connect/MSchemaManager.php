<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\MySQL\Connect;

use Override;
use SilverStripe\ORM\Connect\MySQLSchemaManager;

class MSchemaManager extends MySQLSchemaManager {
    protected final string $indexTemplate = '%s "%s" (%s)';
    protected final string $argTemplate = '%s=%s';
    protected final string $argSeparator = ' ';

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
            if ($index['Index_type'] == 'FULLTEXT') {
                $type = 'fulltext';
            } elseif (!$index['Non_unique']) {
                $type = 'unique';
            } elseif ($index['Index_type'] == 'HASH') {
                $type = 'hash';
            } elseif ($index['Index_type'] == 'RTREE') {
                $type = 'rtree';
            } elseif ($index['Index_type'] == 'VECTOR') {
                $type = 'vector';
            } else {
                $type = 'index';
            }

            $column = $index['Column_name'];
            if (in_array($type, ['index', 'unique'])
                && isset($index['Collation']) && $index['Collation'] !== 'NULL'
            ) {
                $column .= match ($index['Collation']) {
                    'A' => ' ASC',
                    'D' => ' DESC',
                };
            }

            $groupedIndexes[$index['Key_name']]['fields'][$index['Seq_in_index']] = $column;
            $groupedIndexes[$index['Key_name']]['type'] = $type;
        }

        if ($groupedIndexes) {
            foreach ($groupedIndexes as $index => $details) {
                ksort($details['fields']);
                $indexList[$index] = [
                    'name' => $index,
                    'columns' => $details['fields'],
                    'type' => $details['type'],
                ];

                if(array_key_exists('m', $details)) {
                    $indexList[$index]['M'] = $details['M'];
                }

                if(array_key_exists('distance', $details)) {
                    $indexList[$index]['distance'] = $details['distance'];
                }
            }
        }

        return $indexList;
    }

    protected function optionalSqlArg(array &$args, array $spec, string $specKey, string $sqlKey): void {
        if (!array_key_exists('m', $spec)) {
            return; 
        }

        array_push($args, sprintf($this->argTemplate, 'M', $spec['m']));
    }

    protected function optionalSqlArgs(array &$args, array $spec, array $mapping): void {
        foreach ($mapping as $specKey => $sqlKey) {
            $this->optionalSqlArg($args, $spec, $specKey, $sqlKey);
        }
    }

    protected function getIndexSqlDefinition($indexName, $indexSpec)
    {
        if ($indexSpec['type'] == 'using') {
            return sprintf('index "%s" using (%s)', $indexName, $this->implodeIndexColumnList($indexSpec['columns'], $indexSpec['type']));
        } else {
            $indexExp = [sprintf($this->indexTemplate, $indexSpec['type'], $indexName, $this->implodeIndexColumnList($indexSpec['columns'], $indexSpec['type']))];

            $this->optionalSqlArgs($indexExp, $indexSpec, [
                'm' => 'M',
                'distance' => 'DISTANCE'
            ]);

            return implode($this->argSeparator, $indexExp);
        }
    }

    #[Override]
    protected function convertIndexSpec($indexSpec)
    {
        // Return already converted spec
        if (!is_array($indexSpec)
            || !array_key_exists('type', $indexSpec ?? [])
            || !array_key_exists('columns', $indexSpec ?? [])
            || !is_array($indexSpec['columns'])
            || array_key_exists('value', $indexSpec ?? [])
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'argument to convertIndexSpec must be correct indexSpec, %s given',
                    var_export($indexSpec, true)
                )
            );
        }

        $parts = [
            sprintf('%s (%s)', $indexSpec['type'], $this->implodeIndexColumnList($indexSpec['columns'], $indexSpec['type']))
        ];

        $this->optionalSqlArgs($parts, $indexSpec, [
            'm' => 'M',
            'distance' => 'DISTANCE'
        ]);

        return implode($this->argSeparator, $parts);
    }
}

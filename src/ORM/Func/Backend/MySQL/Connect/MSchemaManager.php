<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\MySQL\Connect;

use Override;
use SilverStripe\ORM\Connect\MySQLSchemaManager;

class MSchemaManager extends MySQLSchemaManager
{
    final protected string $indexTemplate = '%s "%s" (%s)';
    final protected string $argTemplate = '%s=%s';
    final protected string $argSeparator = ' ';

    /**
     * Modified version of MySQLSchemaManager's implementation. Maps the vector type and
     * enriches vector indexes with the options MariaDB stored for them.
     */
    #[Override]
    public function indexList($table)
    {
        $indexes = $this->query("SHOW INDEXES IN \"$table\"");
        $groupedIndexes = [];
        $indexList = [];
        $hasVectorIndex = false;

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
                $hasVectorIndex = true;
            } else {
                $type = 'index';
            }

            $column = $index['Column_name'];
            if (
                in_array($type, ['index', 'unique'])
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

        // SHOW INDEXES (and information_schema.STATISTICS) do not carry the vector index
        // options, so they have to come from SHOW CREATE TABLE - but only when there is
        // a vector index to read them for, to avoid a second query for every other table.
        $vectorOptions = $hasVectorIndex ? $this->vectorIndexOptions($table) : [];

        foreach ($groupedIndexes as $index => $details) {
            ksort($details['fields']);
            $indexList[$index] = [
                'name' => $index,
                'columns' => $details['fields'],
                'type' => $details['type'],
            ];

            // Only the options MariaDB actually stored are reported. An absent option is
            // resolved against @@mhnsw_default_m / @@mhnsw_default_distance at query time;
            // filling those values in here would make every spec that omits them compare
            // unequal in requireIndex() and rebuild the HNSW graph on each db:build.
            foreach ($vectorOptions[$index] ?? [] as $option => $value) {
                $indexList[$index][$option] = $value;
            }
        }

        return $indexList;
    }

    /**
     * Stored index options, keyed by index name and then by lowercased option name
     * ('m', 'distance'). Only VECTOR keys carry options, so only those are returned.
     *
     * SHOW CREATE TABLE renders them as `VECTOR KEY "v" ("Col") "M"='6' "DISTANCE"='cosine'`:
     * the option name keeps the case it was created with, the value is always quoted (even
     * the numeric M), and identifiers are double quoted under ANSI_QUOTES - which Silverstripe
     * always sets - but backticked otherwise, so both spellings are accepted here.
     *
     * An option that is missing from the output was never stored; MariaDB then resolves it
     * against @@mhnsw_default_m / @@mhnsw_default_distance when the index is used.
     */
    protected function vectorIndexOptions(string $table): array
    {
        $createTable = $this->showCreateTable($table);
        $options = [];

        preg_match_all(
            '/^\s*VECTOR KEY\s+(?:`((?:[^`]|``)+)`|"((?:[^"]|"")+)")\s*\([^)]*\)([^\r\n]*)/mi',
            $createTable,
            $keys,
            PREG_SET_ORDER
        );

        foreach ($keys as $key) {
            $name = $key[1] !== ''
                ? str_replace('``', '`', $key[1])
                : str_replace('""', '"', $key[2]);
            $options[$name] = [];

            preg_match_all(
                '/(?:`(\w+)`|"(\w+)")\s*=\s*\'((?:[^\']|\'\')*)\'/',
                $key[3],
                $pairs,
                PREG_SET_ORDER
            );

            foreach ($pairs as $pair) {
                $option = strtolower($pair[1] !== '' ? $pair[1] : $pair[2]);
                $options[$name][$option] = str_replace("''", "'", $pair[3]);
            }
        }

        return $options;
    }

    /**
     * SHOW CREATE TABLE output with the engine specific extensions left in.
     *
     * Silverstripe connects with sql_mode=ANSI, under which MariaDB renders portable ANSI
     * SQL and silently drops everything engine specific - ENGINE, AUTO_INCREMENT, CHARSET
     * and, the reason this matters here, the vector index options. ANSI_QUOTES on its own
     * keeps them and keeps identifier quoting intact, so the mode is narrowed for this one
     * statement and restored right afterwards.
     */
    protected function showCreateTable(string $table): string
    {
        $sqlMode = (string) $this->query('SELECT @@session.sql_mode')->value();
        $this->query('SET SESSION sql_mode = ' . $this->database->quoteString('ANSI_QUOTES'));

        try {
            return $this->query("SHOW CREATE TABLE \"$table\"")->record()['Create Table'] ?? '';
        } finally {
            $this->query('SET SESSION sql_mode = ' . $this->database->quoteString($sqlMode));
        }
    }

    protected function optionalSqlArg(array &$args, array $spec, string $specKey, string $sqlKey): void
    {
        if (!array_key_exists($specKey, $spec)) {
            return;
        }

        array_push($args, sprintf($this->argTemplate, $sqlKey, $spec[$specKey]));
    }

    protected function optionalSqlArgs(array &$args, array $spec, array $mapping): void
    {
        foreach ($mapping as $specKey => $sqlKey) {
            $this->optionalSqlArg($args, $spec, $specKey, $sqlKey);
        }
    }

    protected function getIndexSqlDefinition($indexName, $indexSpec)
    {
        if ($indexSpec['type'] == 'using') {
            return sprintf(
                'index "%s" using (%s)',
                $indexName,
                $this->implodeIndexColumnList($indexSpec['columns'], $indexSpec['type'])
            );
        } else {
            $indexExp = [sprintf(
                $this->indexTemplate,
                $indexSpec['type'],
                $indexName,
                $this->implodeIndexColumnList($indexSpec['columns'], $indexSpec['type'])
            )];

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
        if (
            !is_array($indexSpec)
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
            sprintf(
                '%s (%s)',
                $indexSpec['type'],
                $this->implodeIndexColumnList($indexSpec['columns'], $indexSpec['type'])
            )
        ];

        $this->optionalSqlArgs($parts, $indexSpec, [
            'm' => 'M',
            'distance' => 'DISTANCE'
        ]);

        return implode($this->argSeparator, $parts);
    }
}

<?php

namespace Atwx\VectorSearch\Tests\Stub;

use Atwx\VectorSearch\ORM\Func\Backend\MySQL\Connect\MSchemaManager;
use SilverStripe\Dev\TestOnly;

/**
 * MSchemaManager with its query log exposed and its protected helpers reachable, so the
 * tests can assert both what it returns and which statements it needed to get there.
 */
class RecordingSchemaManager extends MSchemaManager implements TestOnly
{
    /** @var string[] */
    public array $queries = [];

    public function query($sql, $errorLevel = E_USER_ERROR)
    {
        $this->queries[] = $sql;

        return parent::query($sql, $errorLevel);
    }

    /**
     * @return string[] every recorded statement matching the given substring
     */
    public function queriesContaining(string $needle): array
    {
        return array_values(array_filter(
            $this->queries,
            fn (string $sql): bool => str_contains($sql, $needle)
        ));
    }

    /**
     * Runs $callback inside a schema update and returns the changes it asked for, without
     * applying any of them.
     */
    public function pendingChanges(callable $callback): array
    {
        $pending = [];

        $this->schemaUpdate(function () use ($callback, &$pending): void {
            $callback();
            $pending = $this->schemaUpdateTransaction ?? [];
            $this->cancelSchemaUpdate();
        });

        return $pending;
    }

    public function callVectorIndexOptions(string $table): array
    {
        return $this->vectorIndexOptions($table);
    }

    public function callShowCreateTable(string $table): string
    {
        return $this->showCreateTable($table);
    }

    public function callGetIndexSqlDefinition(string $indexName, array $indexSpec): string
    {
        return $this->getIndexSqlDefinition($indexName, $indexSpec);
    }

    public function callConvertIndexSpec(mixed $indexSpec): string
    {
        return $this->convertIndexSpec($indexSpec);
    }
}

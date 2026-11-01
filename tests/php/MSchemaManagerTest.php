<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\ORM\Func\Backend\MySQL\Connect\MSchemaManager;
use Atwx\VectorSearch\Tests\Stub\RecordingSchemaManager;
use Atwx\VectorSearch\Tests\Stub\VectorSearchTestDocument;
use Atwx\VectorSearch\Tests\Stub\VectorSearchTestEuclideanDocument;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\ORM\DB;

/**
 * The schema manager is what makes `db:build` aware of vector indexes: it has to report
 * the options MariaDB stored for one, and report them in exactly the shape the index spec
 * uses, or requireIndex() rebuilds the HNSW graph on every build.
 */
final class MSchemaManagerTest extends VectorSearchTestCase
{
    protected static $extra_dataobjects = [
        VectorSearchTestDocument::class,
        VectorSearchTestEuclideanDocument::class,
    ];

    private RecordingSchemaManager $schema;

    /** @var string[] */
    private array $temporaryTables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema = new RecordingSchemaManager();
        $this->schema->setDatabase(DB::get_conn());
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryTables as $table) {
            DB::query("DROP TABLE IF EXISTS \"$table\"");
        }

        parent::tearDown();
    }

    public function testTheSchemaManagerIsBoundByConfiguration(): void
    {
        $this->assertInstanceOf(MSchemaManager::class, DB::get_schema());
    }

    public function testAVectorFieldGetsAVectorIndex(): void
    {
        $index = $this->schema->indexList('VectorSearchTestDocument')['Embedding'];

        $this->assertSame('vector', $index['type']);
        $this->assertSame(['Embedding'], array_values($index['columns']));
    }

    public function testTheIndexCarriesTheDistanceItWasDeclaredWith(): void
    {
        $this->assertSame(
            'cosine',
            $this->schema->indexList('VectorSearchTestDocument')['Embedding']['distance']
        );
        $this->assertSame(
            'euclidean',
            $this->schema->indexList('VectorSearchTestEuclideanDocument')['Embedding']['distance']
        );
    }

    /**
     * MariaDB only stores the options that were given. Reporting @@mhnsw_default_m for an
     * absent M would make every spec that omits it compare unequal, and db:build would
     * rebuild the graph each time.
     */
    public function testAnOptionThatWasNeverStoredIsNotInvented(): void
    {
        $index = $this->schema->indexList('VectorSearchTestDocument')['Embedding'];

        $this->assertArrayNotHasKey('m', $index);
    }

    public function testStoredOptionsAreReportedLowercased(): void
    {
        $this->createTable(
            'VectorOptionsTest',
            '"Embedding" vector(3) not null',
            'VECTOR KEY "Embedding" ("Embedding") M=6 DISTANCE=cosine'
        );

        $index = $this->schema->indexList('VectorOptionsTest')['Embedding'];

        $this->assertSame('vector', $index['type']);
        $this->assertSame('6', $index['m']);
        $this->assertSame('cosine', $index['distance']);
    }

    public function testOptionsAreReadFromShowCreateTable(): void
    {
        $this->createTable(
            'VectorOptionsTest',
            '"Embedding" vector(3) not null',
            'VECTOR KEY "Embedding" ("Embedding") M=8 DISTANCE=euclidean'
        );

        $this->assertSame(
            ['Embedding' => ['m' => '8', 'distance' => 'euclidean']],
            $this->schema->callVectorIndexOptions('VectorOptionsTest')
        );
    }

    /**
     * MariaDB renders the stored options differently between releases: 11.8 quotes the
     * values, 11.7 and 12 leave them bare, and the identifiers are backticked whenever
     * ANSI_QUOTES is not in effect. All of them have to parse to the same thing.
     */
    #[DataProvider('provideVectorKeySpellings')]
    public function testOptionsAreParsedFromEverySpelling(string $keyLine, array $expected): void
    {
        $this->schema->setCreateTable(self::createTableSql($keyLine));

        $this->assertSame($expected, $this->schema->callVectorIndexOptions('VectorOptionsTest'));
    }

    public static function provideVectorKeySpellings(): array
    {
        $options = ['Embedding' => ['m' => '6', 'distance' => 'cosine']];

        return [
            'MariaDB 11.8' => [
                'VECTOR KEY "Embedding" ("Embedding") "M"=\'6\' "DISTANCE"=\'cosine\'',
                $options,
            ],
            'MariaDB 11.7 and 12' => [
                'VECTOR KEY "Embedding" ("Embedding") "M"=6 "DISTANCE"=cosine',
                $options,
            ],
            'backticked identifiers, quoted values' => [
                'VECTOR KEY `Embedding` (`Embedding`) `M`=\'6\' `DISTANCE`=\'cosine\'',
                $options,
            ],
            'backticked identifiers, bare values' => [
                'VECTOR KEY `Embedding` (`Embedding`) `M`=6 `DISTANCE`=cosine',
                $options,
            ],
            'another key follows' => [
                'VECTOR KEY "Embedding" ("Embedding") "M"=6 "DISTANCE"=cosine,',
                $options,
            ],
            'nothing stored' => [
                'VECTOR KEY "Embedding" ("Embedding")',
                ['Embedding' => []],
            ],
            'quotes in the index name' => [
                'VECTOR KEY "Odd""Name" ("Embedding") "DISTANCE"=cosine',
                ['Odd"Name' => ['distance' => 'cosine']],
            ],
            'not a vector key' => [
                'KEY "Title" ("Title")',
                [],
            ],
        ];
    }

    private static function createTableSql(string $keyLine): string
    {
        return "CREATE TABLE \"VectorOptionsTest\" (\n"
            . "  \"ID\" int(11) NOT NULL AUTO_INCREMENT,\n"
            . "  \"Embedding\" vector(3) NOT NULL,\n"
            . "  PRIMARY KEY (\"ID\"),\n"
            . "  $keyLine\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    public function testATableWithoutAVectorIndexCostsNoExtraQuery(): void
    {
        $this->createTable('PlainIndexTest', '"Title" varchar(50) not null default \'\'', 'KEY "Title" ("Title")');

        $this->schema->queries = [];
        $this->schema->indexList('PlainIndexTest');

        $this->assertSame([], $this->schema->queriesContaining('SHOW CREATE TABLE'));
    }

    public function testATableWithAVectorIndexCostsExactlyOneExtraQuery(): void
    {
        $this->schema->queries = [];
        $this->schema->indexList('VectorSearchTestDocument');

        $this->assertCount(1, $this->schema->queriesContaining('SHOW CREATE TABLE'));
    }

    public function testOrdinaryIndexTypesAreStillReported(): void
    {
        $this->createTable(
            'MixedIndexTest',
            '"Title" varchar(50) not null default \'\', "Body" text not null, "Slug" varchar(50) not null default \'\'',
            'UNIQUE KEY "Slug" ("Slug"), KEY "Title" ("Title"), FULLTEXT KEY "Body" ("Body")'
        );

        $indexes = $this->schema->indexList('MixedIndexTest');

        $this->assertSame('unique', $indexes['Slug']['type']);
        $this->assertSame('index', $indexes['Title']['type']);
        $this->assertSame('fulltext', $indexes['Body']['type']);
    }

    public function testMultiColumnIndexesKeepTheirColumnOrder(): void
    {
        $this->createTable(
            'CompositeIndexTest',
            '"A" varchar(10) not null default \'\', "B" varchar(10) not null default \'\'',
            'KEY "AB" ("B", "A")'
        );

        $this->assertSame(
            ['B ASC', 'A ASC'],
            array_values($this->schema->indexList('CompositeIndexTest')['AB']['columns'])
        );
    }

    /**
     * SHOW CREATE TABLE is read under ANSI_QUOTES rather than ANSI, because ANSI drops the
     * vector index options from the output. The session has to be left as it was found.
     */
    public function testTheSessionSqlModeIsRestored(): void
    {
        $before = DB::query('SELECT @@session.sql_mode')->value();

        $this->schema->indexList('VectorSearchTestDocument');

        $this->assertSame($before, DB::query('SELECT @@session.sql_mode')->value());
    }

    public function testTheNarrowedSqlModeKeepsTheOptionsInTheOutput(): void
    {
        $createTable = $this->schema->callShowCreateTable('VectorSearchTestDocument');

        $this->assertStringContainsString('VECTOR KEY', strtoupper($createTable));
        $this->assertStringContainsString('cosine', $createTable);
    }

    /**
     * requireIndex() compares convertIndexSpec() of the declared spec against
     * convertIndexSpec() of what indexList() reports. If those two strings ever drift
     * apart, every db:build alters the index.
     */
    public function testTheStoredIndexConvertsToTheSameStringAsTheDeclaredSpec(): void
    {
        $declared = VectorSearchTestDocument::getSchema()
            ->databaseIndexes(VectorSearchTestDocument::class, false)['Embedding'];
        $stored = $this->schema->indexList('VectorSearchTestDocument')['Embedding'];

        $converted = $this->schema->callConvertIndexSpec($declared);

        $this->assertSame('vector ("Embedding") DISTANCE=cosine', $converted);
        $this->assertSame($converted, $this->schema->callConvertIndexSpec($stored));
    }

    /**
     * The point of reporting only the options MariaDB stored: a second build of an
     * unchanged table must not touch the vector index, because altering it rebuilds the
     * whole HNSW graph.
     */
    public function testAnUnchangedVectorIndexIsNotRebuiltOnTheNextBuild(): void
    {
        $dataObjectSchema = VectorSearchTestDocument::getSchema();

        $changes = $this->schema->pendingChanges(function () use ($dataObjectSchema): void {
            $this->schema->requireTable(
                'VectorSearchTestDocument',
                $dataObjectSchema->databaseFields(VectorSearchTestDocument::class, false),
                $dataObjectSchema->databaseIndexes(VectorSearchTestDocument::class, false)
            );
        });

        $this->assertSame([], $changes['VectorSearchTestDocument']['alteredIndexes'] ?? []);
        $this->assertSame([], $changes['VectorSearchTestDocument']['newIndexes'] ?? []);
    }

    public function testVectorOptionsAreRenderedIntoTheIndexDefinition(): void
    {
        $this->assertSame(
            'vector "Embedding" ("Embedding") M=6 DISTANCE=cosine',
            $this->schema->callGetIndexSqlDefinition('Embedding', [
                'type' => 'vector',
                'columns' => ['Embedding'],
                'm' => 6,
                'distance' => 'cosine',
            ])
        );
    }

    public function testAbsentVectorOptionsAreLeftOutOfTheIndexDefinition(): void
    {
        $this->assertSame(
            'vector "Embedding" ("Embedding")',
            $this->schema->callGetIndexSqlDefinition('Embedding', [
                'type' => 'vector',
                'columns' => ['Embedding'],
            ])
        );
    }

    public function testAnIndexSpecIsConvertedToTheSameShapeItIsComparedAgainst(): void
    {
        $this->assertSame(
            'vector ("Embedding") M=6 DISTANCE=cosine',
            $this->schema->callConvertIndexSpec([
                'type' => 'vector',
                'columns' => ['Embedding'],
                'm' => 6,
                'distance' => 'cosine',
            ])
        );
    }

    public function testConvertingAnIndexSpecWithoutOptionsOmitsThem(): void
    {
        $this->assertSame(
            'vector ("Embedding")',
            $this->schema->callConvertIndexSpec(['type' => 'vector', 'columns' => ['Embedding']])
        );
    }

    #[DataProvider('provideMalformedIndexSpecs')]
    public function testMalformedIndexSpecsAreRejected(mixed $spec): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->schema->callConvertIndexSpec($spec);
    }

    public static function provideMalformedIndexSpecs(): array
    {
        return [
            'not an array' => ['vector ("Embedding")'],
            'no type' => [['columns' => ['Embedding']]],
            'no columns' => [['type' => 'vector']],
            'columns not an array' => [['type' => 'vector', 'columns' => 'Embedding']],
            'already converted' => [['type' => 'vector', 'columns' => ['Embedding'], 'value' => '("Embedding")']],
        ];
    }

    private function createTable(string $table, string $columns, string $keys): void
    {
        $this->temporaryTables[] = $table;

        DB::query("DROP TABLE IF EXISTS \"$table\"");
        DB::query(
            "CREATE TABLE \"$table\" ("
            . '"ID" int not null auto_increment, '
            . $columns . ', '
            . 'PRIMARY KEY ("ID"), ' . $keys
            . ') ENGINE=InnoDB'
        );
    }
}

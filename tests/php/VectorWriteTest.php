<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\ORM\Func\VectorFrom;
use Atwx\VectorSearch\Tests\Stub\VectorSearchTestDocument;
use Atwx\VectorSearch\Tests\Stub\VectorSearchTestEuclideanDocument;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\ORM\DB;

/**
 * Writing a vector through the ORM, end to end: the column MariaDB actually creates, the
 * value that ends up in it, and what happens when the value is wrong.
 */
final class VectorWriteTest extends VectorSearchTestCase
{
    protected static $extra_dataobjects = [
        VectorSearchTestDocument::class,
        VectorSearchTestEuclideanDocument::class,
    ];

    public function testTheColumnIsCreatedAsAVector(): void
    {
        $fields = DB::field_list('VectorSearchTestDocument');

        $this->assertArrayHasKey('Embedding', $fields);
        $this->assertStringContainsString('vector(3)', strtolower($fields['Embedding']));
    }

    public function testAssigningAFunctionCompilesIt(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Embedding = VectorFrom::create([1.0, 2.0, 3.0]);

        $this->assertSame(['VEC_FromText(?)', '[1,2,3]'], $document->getField('Embedding'));
    }

    public function testAssigningAnythingElseIsLeftAlone(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Content = 'plain text';

        $this->assertSame('plain text', $document->Content);
    }

    public function testWritingStoresTheVectorItWasGiven(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Content = 'a document';
        $document->Embedding = VectorFrom::create([1.0, 0.0, 0.0]);
        $document->write();

        $this->assertGreaterThan(0, $document->ID);
        $this->assertSame([1.0, 0.0, 0.0], $this->storedVector($document->ID));
    }

    public function testWritingStoresAnEmbeddedText(): void
    {
        $this->embeddings->setVector('a document', [0.25, 0.5, 0.75]);

        $document = VectorSearchTestDocument::create();
        $document->Content = 'a document';
        $document->Embedding = VectorFrom::single('a document');
        $document->write();

        $this->assertSame([0.25, 0.5, 0.75], $this->storedVector($document->ID));
    }

    public function testABatchOfVectorsCanBeWritten(): void
    {
        $texts = ['first', 'second'];
        $this->embeddings->setVector('first', [1.0, 0.0, 0.0]);
        $this->embeddings->setVector('second', [0.0, 1.0, 0.0]);

        $ids = [];
        foreach (VectorFrom::batch($texts) as $i => $vector) {
            $document = VectorSearchTestDocument::create();
            $document->Content = $texts[$i];
            $document->Embedding = $vector;
            $document->write();
            $ids[] = $document->ID;
        }

        $this->assertSame([1.0, 0.0, 0.0], $this->storedVector($ids[0]));
        $this->assertSame([0.0, 1.0, 0.0], $this->storedVector($ids[1]));
    }

    public function testRewritingReplacesTheVector(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Embedding = VectorFrom::create([1.0, 0.0, 0.0]);
        $document->write();

        $document->Embedding = VectorFrom::create([0.0, 0.0, 1.0]);
        $document->write();

        $this->assertSame([0.0, 0.0, 1.0], $this->storedVector($document->ID));
    }

    public function testWritingWithoutAVectorIsRejected(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Content = 'no embedding';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/NULL is not supported/');
        $document->write();
    }

    public function testWritingARawVectorIsRejected(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Embedding = '[1,2,3]';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/VectorFrom::single/');
        $document->write();
    }

    /**
     * Silverstripe connects with sql_mode=ANSI, which is not a strict mode, so MariaDB
     * downgrades a wrong-length vector to a warning and stores all zeros instead of
     * refusing the row. Nothing downstream can tell that apart from a real embedding;
     * OpenAIEmbeddingsProvider's `validate_dimensions` catches it before the write.
     */
    public function testAVectorOfTheWrongSizeIsSilentlyStoredAsZeroes(): void
    {
        $document = VectorSearchTestDocument::create();
        $document->Embedding = VectorFrom::create([1.0, 2.0]);
        $document->write();

        $this->assertSame([0.0, 0.0, 0.0], $this->storedVector($document->ID));
    }

    /**
     * @return float[]
     */
    private function storedVector(int $id, string $table = 'VectorSearchTestDocument'): array
    {
        $text = DB::prepared_query(
            "SELECT VEC_ToText(\"Embedding\") FROM \"$table\" WHERE \"ID\" = ?",
            [$id]
        )->value();

        return array_map('floatval', json_decode((string) $text, true) ?? []);
    }
}

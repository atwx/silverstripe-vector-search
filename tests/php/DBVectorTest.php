<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\ORM\DBVector;
use Atwx\VectorSearch\ORM\Func\VectorFrom;
use SilverStripe\ORM\FieldType\DBField;

/**
 * The `Vector` database field: its column definition, its index spec, what it accepts as
 * a value and how that value reaches the INSERT.
 */
final class DBVectorTest extends VectorSearchTestCase
{
    public function testVectorIsBoundToTheField(): void
    {
        $this->assertInstanceOf(DBVector::class, DBField::create_field('Vector', null, 'Embedding'));
    }

    public function testTheSizeDefaultsToTheProvidersDimensions(): void
    {
        $this->embeddings->setDimensions(7);

        $this->assertSame('vector(7) not null', (new DBVector('Embedding'))->getFieldSpec());
    }

    public function testAnExplicitSizeWinsOverTheProvider(): void
    {
        $this->embeddings->setDimensions(7);

        $this->assertSame('vector(3) not null', (new DBVector('Embedding', 'euclidean', 3))->getFieldSpec());
    }

    /**
     * Field spec arguments reach the constructor in order, so a single one is the
     * distance and the size stays at the provider's dimensions.
     */
    public function testADistanceOnlyFieldSpecKeepsTheProvidersSize(): void
    {
        $this->embeddings->setDimensions(7);

        $field = DBField::create_field('Vector("cosine")', null, 'Embedding');

        $this->assertSame('vector(7) not null', $field->getFieldSpec());
        $this->assertSame('cosine', $field->getIndexSpecs()['distance']);
    }

    public function testBothFieldSpecArgumentsAreApplied(): void
    {
        $field = DBField::create_field('Vector("cosine", 3)', null, 'Embedding');

        $this->assertSame('vector(3) not null', $field->getFieldSpec());
        $this->assertSame('cosine', $field->getIndexSpecs()['distance']);
    }

    public function testTheIndexSpecDefaultsToEuclidean(): void
    {
        $this->assertSame(
            ['type' => 'vector', 'columns' => ['Embedding'], 'distance' => 'euclidean'],
            (new DBVector('Embedding'))->getIndexSpecs()
        );
    }

    public function testTheIndexSpecCarriesTheConfiguredDistance(): void
    {
        $this->assertSame(
            ['type' => 'vector', 'columns' => ['Embedding'], 'distance' => 'cosine'],
            (new DBVector('Embedding', 'cosine', 3))->getIndexSpecs()
        );
    }

    public function testTheValueIsNotAScalar(): void
    {
        $this->assertFalse((new DBVector('Embedding', 'cosine', 3))->scalarValueOnly());
    }

    public function testNullIsRejectedWithAnExplanation(): void
    {
        $field = new DBVector('Embedding', 'cosine', 3);
        $field->setValue(null);

        $result = $field->validate();

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('NULL is not supported', $this->firstMessage($result));
    }

    public function testARawValueIsRejectedWithAPointerToVectorFrom(): void
    {
        $field = new DBVector('Embedding', 'cosine', 3);
        $field->setValue('[1,2,3]');

        $result = $field->validate();

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('VectorFrom::single', $this->firstMessage($result));
    }

    public function testACompiledFunctionIsAccepted(): void
    {
        $field = new DBVector('Embedding', 'cosine', 3);
        $field->setValue(VectorFrom::create([1.0, 2.0, 3.0])->compile());

        $this->assertTrue($field->validate()->isValid());
    }

    public function testTheManipulationSplitsSqlFromParameters(): void
    {
        $field = new DBVector('Embedding', 'cosine', 3);
        $field->setValue(VectorFrom::create([1.0, 2.0, 3.0])->compile());

        $manipulation = [];
        $field->writeToManipulation($manipulation);

        $this->assertSame(
            ['fields' => ['Embedding' => ['VEC_FromText(?)' => ['[1,2,3]']]]],
            $manipulation
        );
    }

    public function testTheManipulationLeavesTheFieldValueIntact(): void
    {
        $compiled = VectorFrom::create([1.0, 2.0, 3.0])->compile();

        $field = new DBVector('Embedding', 'cosine', 3);
        $field->setValue($compiled);

        $manipulation = [];
        $field->writeToManipulation($manipulation);
        $field->writeToManipulation($manipulation);

        $this->assertSame($compiled, $field->getValue());
        $this->assertSame(
            ['fields' => ['Embedding' => ['VEC_FromText(?)' => ['[1,2,3]']]]],
            $manipulation
        );
    }

    public function testTheManipulationIsMergedIntoExistingFields(): void
    {
        $field = new DBVector('Embedding', 'cosine', 3);
        $field->setValue(VectorFrom::create([1.0])->compile());

        $manipulation = ['fields' => ['Content' => "'kept'"]];
        $field->writeToManipulation($manipulation);

        $this->assertSame("'kept'", $manipulation['fields']['Content']);
        $this->assertSame(['VEC_FromText(?)' => ['[1]']], $manipulation['fields']['Embedding']);
    }

    private function firstMessage(mixed $result): string
    {
        return $result->getMessages()[0]['message'] ?? '';
    }
}

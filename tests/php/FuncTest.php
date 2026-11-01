<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\ORM\Func\Backend\Generic\Distance\GVecDistance;
use Atwx\VectorSearch\ORM\Func\Backend\Generic\Distance\GVecDistanceCosine;
use Atwx\VectorSearch\ORM\Func\Backend\Generic\Distance\GVecDistanceEuclidean;
use Atwx\VectorSearch\ORM\Func\Backend\Generic\GColumn;
use Atwx\VectorSearch\ORM\Func\Backend\Generic\GVectorFrom;
use Atwx\VectorSearch\ORM\Func\Column;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistance;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistanceCosine;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistanceEuclidean;
use Atwx\VectorSearch\ORM\Func\Func;
use Atwx\VectorSearch\ORM\Func\VectorFrom;

/**
 * The compiler that turns a tree of Func objects into one parameterised SQL fragment.
 *
 * A compiled fragment is `[$sql, ...$parameters]`: the SQL first, then one parameter per
 * `?` in it, left to right.
 */
final class FuncTest extends VectorSearchTestCase
{
    public function testAStringArgumentBecomesAParameter(): void
    {
        $this->assertSame(['?', 'plain'], Func::compileObj('plain'));
    }

    public function testAFuncArgumentIsCompiledRecursively(): void
    {
        $inner = GVectorFrom::create([1.0, 2.0]);

        $this->assertSame($inner->compile(), Func::compileObj($inner));
    }

    public function testArgumentsAreSeparatedAndParametersFollowInOrder(): void
    {
        // The base Func has no name of its own, so only the parentheses show up.
        $this->assertSame(['(?,?)', 'a', 'b'], Func::create('a', 'b')->compile());
    }

    public function testAFunctionWithoutArgumentsCompilesToEmptyParentheses(): void
    {
        $this->assertSame(['()'], Func::create()->compile());
    }

    public function testNestedFunctionsFlattenParametersLeftToRight(): void
    {
        $distance = GVecDistanceCosine::create(
            GColumn::create('Embedding'),
            GVectorFrom::create([1.0, 2.0, 3.0])
        );

        $this->assertSame(
            ['VEC_DISTANCE_COSINE("Embedding",VEC_FromText(?))', '[1,2,3]'],
            $distance->compile()
        );
    }

    public function testParametersOfSeveralNestedFunctionsKeepTheirPositions(): void
    {
        $nested = Func::create(
            GVectorFrom::create([1.0]),
            'between',
            GVectorFrom::create([2.0])
        );

        $this->assertSame(
            ['(VEC_FromText(?),?,VEC_FromText(?))', '[1]', 'between', '[2]'],
            $nested->compile()
        );
    }

    public function testAColumnIsEscapedAsAnIdentifierAndNeverParameterised(): void
    {
        $this->assertSame(['"Embedding"'], GColumn::create('Embedding')->compile());
    }

    public function testAQualifiedColumnIsEscapedPerSegment(): void
    {
        $this->assertSame(
            ['"VectorSearchTestDocument"."Embedding"'],
            GColumn::create('VectorSearchTestDocument.Embedding')->compile()
        );
    }

    /**
     * Passing the column as a plain string makes it a bound parameter, not an identifier -
     * which is the whole reason Column exists.
     */
    public function testAStringArgumentIsNotTreatedAsAColumn(): void
    {
        $distance = GVecDistance::create('Embedding', GVectorFrom::create([1.0]));

        $this->assertSame(['VEC_DISTANCE(?,VEC_FromText(?))', 'Embedding', '[1]'], $distance->compile());
    }

    public function testCreateResolvesTheBackendBoundByConfiguration(): void
    {
        $column = Column::create('Embedding');
        $vector = VectorFrom::create([1.0]);

        $this->assertInstanceOf(GColumn::class, $column);
        $this->assertInstanceOf(GVectorFrom::class, $vector);
        $this->assertInstanceOf(GVecDistance::class, VecDistance::create($column, $vector));
        $this->assertInstanceOf(GVecDistanceCosine::class, VecDistanceCosine::create($column, $vector));
        $this->assertInstanceOf(GVecDistanceEuclidean::class, VecDistanceEuclidean::create($column, $vector));
    }

    public function testEachDistanceBackendUsesItsOwnSqlFunction(): void
    {
        $column = Column::create('Embedding');
        $vector = VectorFrom::create([1.0]);

        $this->assertSame(
            'VEC_DISTANCE("Embedding",VEC_FromText(?))',
            VecDistance::create($column, $vector)->compile()[0]
        );
        $this->assertSame(
            'VEC_DISTANCE_COSINE("Embedding",VEC_FromText(?))',
            VecDistanceCosine::create($column, $vector)->compile()[0]
        );
        $this->assertSame(
            'VEC_DISTANCE_EUCLIDEAN("Embedding",VEC_FromText(?))',
            VecDistanceEuclidean::create($column, $vector)->compile()[0]
        );
    }
}

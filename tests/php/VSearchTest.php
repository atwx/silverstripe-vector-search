<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\ORM\Func\Column;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistance;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistanceCosine;
use Atwx\VectorSearch\ORM\Func\Distance\VecDistanceEuclidean;
use Atwx\VectorSearch\ORM\Func\Func;
use Atwx\VectorSearch\ORM\Func\VectorFrom;
use Atwx\VectorSearch\ORM\Helpers\VSearch;
use Atwx\VectorSearch\Tests\Stub\VectorSearchTestDocument;
use Atwx\VectorSearch\Tests\Stub\VectorSearchTestEuclideanDocument;
use SilverStripe\ORM\DataList;

/**
 * Similarity search, run against MariaDB rather than asserted as SQL strings: the point of
 * these is that the generated expression is something the database actually accepts and
 * that the ordering it produces is the one callers expect.
 */
final class VSearchTest extends VectorSearchTestCase
{
    protected static $extra_dataobjects = [
        VectorSearchTestDocument::class,
        VectorSearchTestEuclideanDocument::class,
    ];

    /** @var array<string, float[]> */
    private const CORPUS = [
        'x axis' => [1.0, 0.0, 0.0],
        'y axis' => [0.0, 1.0, 0.0],
        'z axis' => [0.0, 0.0, 1.0],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::CORPUS as $content => $vector) {
            foreach ([VectorSearchTestDocument::class, VectorSearchTestEuclideanDocument::class] as $class) {
                $document = $class::create();
                $document->Content = $content;
                $document->Embedding = VectorFrom::create($vector);
                $document->write();
            }
        }
    }

    public function testAnnotationIsSelectedUnderTheGivenName(): void
    {
        $list = VSearch::annotateQuery(
            VectorSearchTestEuclideanDocument::get(),
            'distance',
            VecDistanceEuclidean::create(Column::create('Embedding'), VectorFrom::create([1.0, 0.0, 0.0]))
        );

        $this->assertStringContainsString('VEC_DISTANCE_EUCLIDEAN', $list->dataQuery()->sql());
        $this->assertSame(0.0, (float) $list->filter('Content', 'x axis')->first()->distance);
    }

    public function testTheVectorIsInlinedRatherThanBound(): void
    {
        $sql = VSearch::annotateQuery(
            VectorSearchTestEuclideanDocument::get(),
            'distance',
            VecDistanceEuclidean::create(Column::create('Embedding'), VectorFrom::create([1.0, 0.0, 0.0]))
        )->dataQuery()->sql($parameters);

        $this->assertStringContainsString("VEC_FromText('[1,0,0]')", $sql);
        $this->assertSame([], $parameters);
    }

    /**
     * Parameters are inlined instead of bound, so the escaping has to hold on its own.
     */
    public function testInlinedParametersAreEscaped(): void
    {
        $list = VSearch::annotateQuery(
            VectorSearchTestEuclideanDocument::get(),
            'quoted',
            Func::create("O'Brien \\ \"quoted\"")
        );

        $this->assertSame("O'Brien \\ \"quoted\"", $list->first()->quoted);
    }

    public function testEuclideanDistancesAreComputed(): void
    {
        $distances = $this->distances(
            VectorSearchTestEuclideanDocument::get(),
            VecDistanceEuclidean::create(Column::create('Embedding'), VectorFrom::create([1.0, 0.0, 0.0]))
        );

        $this->assertEqualsWithDelta(0.0, $distances['x axis'], 0.0001);
        $this->assertEqualsWithDelta(sqrt(2), $distances['y axis'], 0.0001);
        $this->assertEqualsWithDelta(sqrt(2), $distances['z axis'], 0.0001);
    }

    public function testCosineDistancesAreComputed(): void
    {
        $distances = $this->distances(
            VectorSearchTestEuclideanDocument::get(),
            VecDistanceCosine::create(Column::create('Embedding'), VectorFrom::create([1.0, 1.0, 0.0]))
        );

        $this->assertEqualsWithDelta(1 - 1 / sqrt(2), $distances['x axis'], 0.0001);
        $this->assertEqualsWithDelta(1 - 1 / sqrt(2), $distances['y axis'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $distances['z axis'], 0.0001);
    }

    public function testSortByDistanceReturnsTheNearestFirst(): void
    {
        $this->embeddings->setVector('something like x', [0.9, 0.1, 0.0]);

        $list = VSearch::sortByDistance(VectorSearchTestDocument::get(), 'Embedding', 'something like x');

        $this->assertSame('x axis', $list->first()->Content);
        $this->assertSame('z axis', $list->last()->Content);
    }

    public function testSortByDistanceEmbedsTheSearchTextExactlyOnce(): void
    {
        VSearch::sortByDistance(VectorSearchTestDocument::get(), 'Embedding', 'something like x')->toArray();

        $this->assertSame(['something like x'], $this->embeddings->embedCalls);
    }

    public function testSortByDistanceAnnotatesEachRow(): void
    {
        $this->embeddings->setVector('something like x', [1.0, 0.0, 0.0]);

        $list = VSearch::sortByDistance(VectorSearchTestDocument::get(), 'Embedding', 'something like x');

        $this->assertEqualsWithDelta(0.0, (float) $list->first()->embedding_distance, 0.0001);
    }

    public function testSortByDistanceAcceptsADirection(): void
    {
        $this->embeddings->setVector('something like x', [0.9, 0.1, 0.0]);

        $list = VSearch::sortByDistance(
            VectorSearchTestDocument::get(),
            'Embedding',
            'something like x',
            'DESC'
        );

        $this->assertSame('z axis', $list->first()->Content);
    }

    public function testSortByDistanceAcceptsAnAnnotationName(): void
    {
        $this->embeddings->setVector('something like x', [1.0, 0.0, 0.0]);

        $list = VSearch::sortByDistance(
            VectorSearchTestDocument::get(),
            'Embedding',
            'something like x',
            'ASC',
            'score'
        );

        $this->assertEqualsWithDelta(0.0, (float) $list->first()->score, 0.0001);
    }

    /**
     * VEC_DISTANCE() has no metric of its own - it takes the one stored on the column's
     * index, which is what makes the two fixtures differ.
     */
    public function testVecDistanceFollowsTheIndexMetric(): void
    {
        $query = VecDistance::create(Column::create('Embedding'), VectorFrom::create([1.0, 1.0, 0.0]));

        $cosine = $this->distances(VectorSearchTestDocument::get(), $query);
        $euclidean = $this->distances(VectorSearchTestEuclideanDocument::get(), $query);

        $this->assertEqualsWithDelta(1 - 1 / sqrt(2), $cosine['x axis'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $euclidean['x axis'], 0.0001);
    }

    public function testTheResultIsStillAFilterableList(): void
    {
        $this->embeddings->setVector('something like x', [0.9, 0.1, 0.0]);

        $list = VSearch::sortByDistance(VectorSearchTestDocument::get(), 'Embedding', 'something like x')
            ->exclude('Content', 'x axis')
            ->limit(1);

        $this->assertCount(1, $list);
        $this->assertSame('y axis', $list->first()->Content);
    }

    /**
     * @return array<string, float>
     */
    private function distances(DataList $list, Func $distance): array
    {
        $annotated = VSearch::annotateQuery($list, 'distance', $distance);
        $distances = [];

        foreach ($annotated as $document) {
            $distances[$document->Content] = (float) $document->distance;
        }

        return $distances;
    }
}

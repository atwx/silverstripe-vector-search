<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\ORM\Func\Backend\Generic\GVectorFrom;
use Atwx\VectorSearch\ORM\Func\VectorFrom;

/**
 * VectorFrom carries an already-computed vector into SQL, and is the only place in the
 * module that reaches for the embeddings provider.
 */
final class VectorFromTest extends VectorSearchTestCase
{
    public function testTheVectorIsSentAsASingleJsonParameter(): void
    {
        $this->assertSame(
            ['VEC_FromText(?)', '[0.1,0.2,0.3]'],
            VectorFrom::create([0.1, 0.2, 0.3])->compile()
        );
    }

    public function testAnEmptyVectorStillCompiles(): void
    {
        $this->assertSame(['VEC_FromText(?)', '[]'], VectorFrom::create([])->compile());
    }

    public function testSingleEmbedsTheTextExactlyOnce(): void
    {
        $this->embeddings->setVector('a needle', [1.0, 2.0, 3.0]);

        $vector = VectorFrom::single('a needle');

        $this->assertSame(['a needle'], $this->embeddings->embedCalls);
        $this->assertSame([], $this->embeddings->embedBatchCalls);
        $this->assertSame(['VEC_FromText(?)', '[1,2,3]'], $vector->compile());
    }

    public function testBatchEmbedsEverythingInOneCall(): void
    {
        $this->embeddings->setVector('one', [1.0, 0.0, 0.0]);
        $this->embeddings->setVector('two', [0.0, 1.0, 0.0]);

        VectorFrom::batch(['one', 'two']);

        $this->assertSame([['one', 'two']], $this->embeddings->embedBatchCalls);
        $this->assertSame([], $this->embeddings->embedCalls);
    }

    public function testBatchKeepsInputOrder(): void
    {
        $this->embeddings->setVector('one', [1.0, 0.0, 0.0]);
        $this->embeddings->setVector('two', [0.0, 1.0, 0.0]);

        $vectors = VectorFrom::batch(['one', 'two']);

        $this->assertCount(2, $vectors);
        $this->assertSame(['VEC_FromText(?)', '[1,0,0]'], $vectors[0]->compile());
        $this->assertSame(['VEC_FromText(?)', '[0,1,0]'], $vectors[1]->compile());
    }

    public function testAnEmptyBatchReturnsNothing(): void
    {
        $this->assertSame([], VectorFrom::batch([]));
    }

    public function testTheBackendBoundByConfigurationIsUsed(): void
    {
        $this->assertInstanceOf(GVectorFrom::class, VectorFrom::single('a needle'));
        $this->assertInstanceOf(GVectorFrom::class, VectorFrom::batch(['a needle'])[0]);
    }
}

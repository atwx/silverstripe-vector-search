<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\Embedding\EmbeddingsProvider;
use Atwx\VectorSearch\Tests\Stub\StubEmbeddingsProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Base class for the tests that need an embeddings provider. The stub is registered for
 * the whole test, so nothing in the module can reach a real API.
 *
 * PHPUnit collects `*Test.php`, which is why this file is not named that.
 */
abstract class VectorSearchTestCase extends SapphireTest
{
    protected StubEmbeddingsProvider $embeddings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->embeddings = new StubEmbeddingsProvider();
        Injector::inst()->registerService($this->embeddings, EmbeddingsProvider::class);
    }
}

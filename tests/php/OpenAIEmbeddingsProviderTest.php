<?php

namespace Atwx\VectorSearch\Tests;

use Atwx\VectorSearch\Embedding\EmbeddingsProvider;
use Atwx\VectorSearch\Embedding\OpenAIEmbeddingsProvider;
use OpenAI\Resources\Embeddings;
use OpenAI\Responses\Embeddings\CreateResponse;
use OpenAI\Testing\ClientFake;
use OpenAI\Testing\Enums\OverrideStrategy;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

final class OpenAIEmbeddingsProviderTest extends TestCase
{
    /**
     * Boots the Silverstripe kernel so `Config` is available. SapphireTest itself is
     * not used here: it insists on a temp database, and none of this needs one.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        SapphireTest::start();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::nest();
    }

    protected function tearDown(): void
    {
        Config::unnest();
        parent::tearDown();
    }

    /**
     * @param array<int, array{int, float[]}> $indexedVectors [index, vector] pairs
     */
    private static function response(array $indexedVectors): CreateResponse
    {
        return CreateResponse::fake(
            [
                'object' => 'list',
                'model' => 'test-model',
                'data' => array_map(
                    fn (array $pair): array => [
                        'object' => 'embedding',
                        'index' => $pair[0],
                        'embedding' => $pair[1],
                    ],
                    $indexedVectors
                ),
            ],
            strategy: OverrideStrategy::Replace
        );
    }

    private static function provider(ClientFake $client): OpenAIEmbeddingsProvider
    {
        return (new OpenAIEmbeddingsProvider())->setClient($client);
    }

    public function testShippedDefaultsAreWired(): void
    {
        $config = Config::inst()->get(OpenAIEmbeddingsProvider::class);

        $this->assertSame('https://api.openai.com/v1', $config['base_uri']);
        $this->assertSame('OPENAI_API_KEY', $config['api_key_env']);
        $this->assertSame('text-embedding-3-small', $config['model']);
        $this->assertSame(1536, $config['dimensions']);

        $this->assertInstanceOf(
            OpenAIEmbeddingsProvider::class,
            Injector::inst()->get(EmbeddingsProvider::class)
        );
    }

    public function testBatchIsReturnedInInputOrder(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 3);

        // Providers are allowed to answer out of order; `index` is what carries the pairing.
        $provider = self::provider(new ClientFake([
            self::response([[1, [4.0, 5.0, 6.0]], [0, [1.0, 2.0, 3.0]]]),
        ]));

        $this->assertSame([[1.0, 2.0, 3.0], [4.0, 5.0, 6.0]], $provider->embedBatch(['a', 'b']));
    }

    public function testEmbedReturnsASingleVector(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 3);

        $provider = self::provider(new ClientFake([self::response([[0, [1.0, 2.0, 3.0]]])]));

        $this->assertSame([1.0, 2.0, 3.0], $provider->embed('a'));
    }

    public function testEmptyBatchMakesNoRequest(): void
    {
        $client = new ClientFake();

        $this->assertSame([], self::provider($client)->embedBatch([]));
        $client->assertNothingSent();
    }

    public function testBatchSizeSplitsIntoSeveralRequests(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 1);
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'batch_size', 2);

        $client = new ClientFake([
            self::response([[0, [1.0]], [1, [2.0]]]),
            self::response([[0, [3.0]]]),
        ]);

        $this->assertSame([[1.0], [2.0], [3.0]], self::provider($client)->embedBatch(['a', 'b', 'c']));
        $client->assertSent(Embeddings::class, 2);
    }

    public function testModelAndDimensionsAreSentWhenConfigured(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'model', 'some/embedding-model');
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 2);
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'send_dimensions', true);
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'extra_parameters', ['encoding_format' => 'float']);

        $client = new ClientFake([self::response([[0, [1.0, 2.0]]])]);
        self::provider($client)->embed('a');

        $client->assertSent(Embeddings::class, function (string $method, array $parameters): bool {
            $this->assertSame('create', $method);
            $this->assertSame('some/embedding-model', $parameters['model']);
            $this->assertSame(['a'], $parameters['input']);
            $this->assertSame(2, $parameters['dimensions']);
            $this->assertSame('float', $parameters['encoding_format']);

            return true;
        });
    }

    public function testDimensionsAreOmittedByDefault(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 2);

        $client = new ClientFake([self::response([[0, [1.0, 2.0]]])]);
        self::provider($client)->embed('a');

        $client->assertSent(Embeddings::class, function (string $method, array $parameters): bool {
            $this->assertArrayNotHasKey('dimensions', $parameters);

            return true;
        });
    }

    public function testUnexpectedVectorSizeIsReported(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 1536);

        $provider = self::provider(new ClientFake([self::response([[0, [1.0, 2.0, 3.0]]])]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/returned 3 dimensions.*configured for 1536/s');
        $provider->embed('a');
    }

    public function testUnexpectedVectorSizeIsAllowedWhenValidationIsOff(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 1536);
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'validate_dimensions', false);

        $provider = self::provider(new ClientFake([self::response([[0, [1.0, 2.0, 3.0]]])]));

        $this->assertSame([1.0, 2.0, 3.0], $provider->embed('a'));
    }

    public function testShortResponseIsReported(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'dimensions', 1);

        $provider = self::provider(new ClientFake([self::response([[0, [1.0]]])]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected 2 embeddings.*got 1/s');
        $provider->embedBatch(['a', 'b']);
    }

    public function testMissingApiKeyNamesTheConfiguredEnvVariable(): void
    {
        Config::modify()->set(OpenAIEmbeddingsProvider::class, 'api_key_env', 'S2EMBED_UNSET_KEY');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('S2EMBED_UNSET_KEY is not set in .env');
        (new OpenAIEmbeddingsProvider())->embed('a');
    }
}

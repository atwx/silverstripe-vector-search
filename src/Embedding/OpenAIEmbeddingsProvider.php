<?php

namespace Atwx\VectorSearch\Embedding;

use GuzzleHttp\Client as GuzzleClient;
use OpenAI;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\ServerException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Exceptions\UnserializableResponse;
use Psr\Http\Client\ClientInterface;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

/**
 * Default EmbeddingsProvider: calls any OpenAI-compatible `/embeddings` endpoint
 * through openai-php/client.
 *
 * It is not tied to OpenAI itself — point `base_uri` at OpenRouter, Requesty,
 * Azure OpenAI, a local Ollama/LM Studio server, or anything else speaking the
 * same API, and set `api_key_env` to the env var holding that service's key.
 *
 * The API key is always read from the environment (`.env`), never from YAML —
 * config files are committed, keys are not.
 *
 * If you change `model` to one with a different output size, update `dimensions`
 * too AND the `Vector(<size>)` column on your DataObject to match, or writes will
 * fail (the VECTOR column has a fixed size).
 *
 * @author Sebi <https://github.com/ItsSebis>
 */
class OpenAIEmbeddingsProvider implements EmbeddingsProvider
{
    use Configurable;
    use Injectable;

    /**
     * Base URI of the OpenAI-compatible API, including the version segment.
     * `/embeddings` is appended to it. A URI without a scheme is treated as https.
     */
    private static string $base_uri = 'https://api.openai.com/v1';

    /**
     * Name of the environment variable holding the API key.
     */
    private static string $api_key_env = 'OPENAI_API_KEY';

    private static string $model = 'text-embedding-3-small';

    /**
     * Expected output size of the model. Also the default size of `Vector` columns.
     */
    private static int $dimensions = 1536;

    /**
     * Send `dimensions` as a request parameter to shorten the returned vectors.
     * Only supported by some models (e.g. OpenAI's text-embedding-3-*); providers
     * that don't support it may reject the request outright.
     */
    private static bool $send_dimensions = false;

    /**
     * Throw if the API returns vectors of a size other than `dimensions`, instead of
     * letting the database reject them later with a less obvious error.
     */
    private static bool $validate_dimensions = true;

    /**
     * Largest number of texts to send in one request. 0 sends them all in a single
     * call. Set this when your provider caps the batch size (OpenAI allows 2048).
     */
    private static int $batch_size = 0;

    /**
     * Sent as the `OpenAI-Organization` / `OpenAI-Project` headers. OpenAI only.
     */
    private static ?string $organization = null;

    private static ?string $project = null;

    /**
     * Extra HTTP headers, e.g. OpenRouter's attribution headers:
     *   http_headers:
     *     HTTP-Referer: 'https://example.com'
     *     X-Title: 'My Site'
     *
     * @var array<string, string>
     */
    private static array $http_headers = [];

    /**
     * Extra query string parameters, e.g. Azure OpenAI's `api-version`.
     *
     * @var array<string, string>
     */
    private static array $query_params = [];

    /**
     * Extra parameters merged into the request body, e.g. `encoding_format` or a
     * provider-specific `input_type`. `model` and `input` always win over these.
     *
     * @var array<string, mixed>
     */
    private static array $extra_parameters = [];

    /**
     * Request and connection timeouts in seconds.
     */
    private static int $timeout = 30;

    private static int $connect_timeout = 10;

    private ?ClientContract $client = null;

    public function getDimensions(): int
    {
        return (int) static::config()->get('dimensions');
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $texts = array_values($texts);
        $batchSize = (int) static::config()->get('batch_size');
        $chunks = $batchSize > 0 ? array_chunk($texts, $batchSize) : [$texts];

        $embeddings = [];
        foreach ($chunks as $chunk) {
            array_push($embeddings, ...$this->requestEmbeddings($chunk));
        }

        return $embeddings;
    }

    /**
     * Lets tests and projects supply a pre-built client (e.g. OpenAI::fake()).
     */
    public function setClient(?ClientContract $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getClient(): ClientContract
    {
        return $this->client ??= $this->createClient();
    }

    /**
     * One API call. Returns one vector per input, in input order.
     *
     * @param string[] $texts
     * @return float[][]
     */
    protected function requestEmbeddings(array $texts): array
    {
        $config = static::config();

        $parameters = array_merge((array) $config->get('extra_parameters'), [
            'model' => (string) $config->get('model'),
            // The /embeddings endpoint accepts a batch of strings in one call.
            'input' => $texts,
        ]);

        if ($config->get('send_dimensions')) {
            $parameters['dimensions'] = $this->getDimensions();
        }

        try {
            $response = $this->getClient()->embeddings()->create($parameters);
        } catch (
            ErrorException
            | RateLimitException
            | ServerException
            | TransporterException
            | UnserializableResponse $e
        ) {
            throw new RuntimeException(sprintf(
                'Embeddings request to %s failed: %s',
                $config->get('base_uri'),
                $e->getMessage()
            ), 0, $e);
        }

        // Not every provider returns the embeddings in input order, but each one
        // carries the index of the input it belongs to.
        $vectors = [];
        foreach ($response->embeddings as $position => $embedding) {
            $vectors[$embedding->index ?? $position] = $embedding->embedding;
        }
        ksort($vectors);
        $vectors = array_values($vectors);

        if (count($vectors) !== count($texts)) {
            throw new RuntimeException(sprintf(
                'Expected %d embeddings from %s, got %d',
                count($texts),
                $config->get('base_uri'),
                count($vectors)
            ));
        }

        if ($config->get('validate_dimensions')) {
            $expected = $this->getDimensions();
            foreach ($vectors as $vector) {
                if (count($vector) !== $expected) {
                    throw new RuntimeException(sprintf(
                        'Model %s returned %d dimensions, but %s is configured for %d. '
                            . 'Update the `dimensions` config and the Vector(<size>) column to match.',
                        $config->get('model'),
                        count($vector),
                        static::class,
                        $expected
                    ));
                }
            }
        }

        return $vectors;
    }

    protected function createClient(): ClientContract
    {
        $config = static::config();

        $factory = OpenAI::factory()
            ->withApiKey($this->getApiKey())
            ->withBaseUri((string) $config->get('base_uri'))
            ->withHttpClient($this->createHttpClient());

        if ($organization = $config->get('organization')) {
            $factory = $factory->withOrganization((string) $organization);
        }

        if ($project = $config->get('project')) {
            $factory = $factory->withProject((string) $project);
        }

        foreach ((array) $config->get('http_headers') as $name => $value) {
            $factory = $factory->withHttpHeader((string) $name, (string) $value);
        }

        foreach ((array) $config->get('query_params') as $name => $value) {
            $factory = $factory->withQueryParam((string) $name, (string) $value);
        }

        return $factory->make();
    }

    protected function createHttpClient(): ClientInterface
    {
        return new GuzzleClient([
            'timeout' => (int) static::config()->get('timeout'),
            'connect_timeout' => (int) static::config()->get('connect_timeout'),
        ]);
    }

    protected function getApiKey(): string
    {
        $variable = (string) static::config()->get('api_key_env');
        $apiKey = Environment::getEnv($variable);

        if (!is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException(sprintf('%s is not set in .env', $variable));
        }

        return $apiKey;
    }
}

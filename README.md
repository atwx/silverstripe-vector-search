# silverstripe-vector-search

Vector embeddings and similarity search for the Silverstripe ORM, using MariaDB's native `VECTOR` type.

S2 Embed adds a `Vector` field type, turns text into embeddings through a pluggable provider (any OpenAI-compatible API by default), and orders `DataList` by vector distance. It is a good fit for semantic search, "related content", and retrieval for RAG setups.

## Requirements

- Silverstripe Framework 6
- MariaDB 11.7 or newer (the first release with `VECTOR` columns and vector indexes)
- An API key for an OpenAI-compatible embeddings API — OpenAI, [OpenRouter](https://openrouter.ai), [Requesty](https://requesty.ai), Azure OpenAI, a local Ollama/LM Studio server — or your own embeddings provider (see [Using a different embeddings provider](#using-a-different-embeddings-provider))

## Installation

```bash
composer require atwx/s2embed
```

Add your API key to `.env`:

```dotenv
OPENAI_API_KEY="sk-..."
```

The default endpoint is OpenAI's. To use a different service, see [Configuration](#configuration).

## Usage

### 1. Add a vector field

```php
use SilverStripe\ORM\DataObject;

class Document extends DataObject
{
    use SupportsVFuncs;
    
    private static string $table_name = 'Document';

    private static array $db = [
        'Content' => 'Text',
        'Embedding' => 'Vector',
    ];

    private static array $indexes = [
        'Embedding' => true,
    ];
}
```

The column defaults to 1536 dimensions. If your model produces a different size, set it explicitly, e.g. `'Embedding' => 'Vector(768)'`. Indexed vector fields use cosine distance.

Run `sake db:build --flush` to create the column and index.

### 2. Store an embedding

```php
use Atwx\VectorSearch\ORM\Func\Generic\VectorFrom;

$doc = Document::create();
$doc->Content = $text;
$doc->Embedding = VectorFrom::single($text);
$doc->write();
```

The column is `NOT NULL`, so every record needs an embedding before it is written.

To embed many texts with one API call, use `VectorFrom::batch()`. It returns one `VectorFrom` per input, in the same order:

```php
$vectors = VectorFrom::batch($texts);

foreach ($texts as $i => $text) {
    $doc = Document::create();
    $doc->Content = $text;
    $doc->Embedding = $vectors[$i];
    $doc->write();
}
```

### 3. Search by similarity

```php
use Atwx\VectorSearch\ORM\Helpers\VSearch;

$results = VSearch::sort_by_distance(Document::get(), 'Embedding', $userInput)->limit(5);

foreach ($results as $doc) {
    echo $doc->embedding_distance; // lower is more similar
}
```

`sort_by_distance()` embeds the search text, adds the distance to each row as `embedding_distance`, and sorts ascending. Pass a sort direction and an annotation name as the 4th and 5th arguments to change either.

### Custom distance queries

`VSearch::annotate_query()` adds any SQL function expression to the query as a named field. The distance functions live in `Atwx\VectorSearch\ORM\Func\Generic\Distance`:

| Class                  | SQL function              |
| ---------------------- | ------------------------- |
| `VecDistance`          | `VEC_DISTANCE` (uses the index's metric) |
| `VecDistanceCosine`    | `VEC_DISTANCE_COSINE`     |
| `VecDistanceEuclidean` | `VEC_DISTANCE_EUCLIDEAN`  |

```php
use Atwx\VectorSearch\ORM\Func\Generic\Column;
use Atwx\VectorSearch\ORM\Func\Generic\Distance\VecDistanceEuclidean;
use Atwx\VectorSearch\ORM\Func\Generic\VectorFrom;
use Atwx\VectorSearch\ORM\Helpers\VSearch;

$list = VSearch::annotate_query(
    Document::get(),
    'distance',
    new VecDistanceEuclidean(new Column('Embedding'), VectorFrom::single($userInput))
)->sort('distance', 'ASC');
```

## Configuration

The default provider calls any OpenAI-compatible `/embeddings` endpoint through
[openai-php/client](https://github.com/openai-php/client). Defaults ship in
[`_config/embeddings.yml`](_config/embeddings.yml); override them in your project's config:

```yaml
Atwx\VectorSearch\Embedding\OpenAIEmbeddingsProvider:
  model: 'text-embedding-3-small'
  dimensions: 1536
```

| Option                | Default                      | Purpose                                                                                                                      |
| --------------------- | ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `base_uri`            | `https://api.openai.com/v1`  | Base URI of the API, including the version segment. `/embeddings` is appended. A URI without a scheme is treated as `https`.   |
| `api_key_env`         | `OPENAI_API_KEY`             | Name of the `.env` variable holding the API key. The key itself is never read from YAML.                                       |
| `model`               | `text-embedding-3-small`     | Model identifier, in whatever form the service expects.                                                                        |
| `dimensions`          | `1536`                       | Expected output size. Also the default size of `Vector` columns.                                                               |
| `send_dimensions`     | `false`                      | Send `dimensions` as a request parameter to shorten the vectors. Only some models support it; others reject the request.        |
| `validate_dimensions` | `true`                       | Throw if the API returns a different vector size, instead of letting MariaDB reject the write with a less obvious error.        |
| `batch_size`          | `0`                          | Largest number of texts per request; `0` sends them all in one call. Set it when your provider caps the batch size.             |
| `organization`        | `null`                       | Sent as `OpenAI-Organization`. OpenAI only.                                                                                    |
| `project`             | `null`                       | Sent as `OpenAI-Project`. OpenAI only.                                                                                         |
| `http_headers`        | `[]`                         | Extra HTTP headers, e.g. OpenRouter's attribution headers.                                                                     |
| `query_params`        | `[]`                         | Extra query string parameters, e.g. Azure OpenAI's `api-version`.                                                              |
| `extra_parameters`    | `[]`                         | Extra parameters merged into the request body, e.g. `encoding_format`. `model` and `input` always win over these.               |
| `timeout`             | `30`                         | Request timeout in seconds.                                                                                                    |
| `connect_timeout`     | `10`                         | Connection timeout in seconds.                                                                                                 |

If you switch to a model with a different output size, update `dimensions` **and** the
`Vector(<size>)` field to match. MariaDB will reject vectors whose length doesn't match the column.

### Examples

**OpenRouter**

```yaml
Atwx\VectorSearch\Embedding\OpenAIEmbeddingsProvider:
  base_uri: 'https://openrouter.ai/api/v1'
  api_key_env: 'OPENROUTER_API_KEY'
  model: 'openai/text-embedding-3-small'
  dimensions: 1536
  http_headers:
    HTTP-Referer: 'https://example.com'
    X-Title: 'My Site'
```

**Requesty**

```yaml
Atwx\VectorSearch\Embedding\OpenAIEmbeddingsProvider:
  base_uri: 'https://router.requesty.ai/v1'
  api_key_env: 'REQUESTY_API_KEY'
  model: 'openai/text-embedding-3-small'
  dimensions: 1536
```

**Azure OpenAI**

```yaml
Atwx\VectorSearch\Embedding\OpenAIEmbeddingsProvider:
  base_uri: 'https://my-resource.openai.azure.com/openai/deployments/my-embedding-deployment'
  api_key_env: 'AZURE_OPENAI_API_KEY'
  model: 'text-embedding-3-small'
  dimensions: 1536
  query_params:
    api-version: '2024-02-01'
```

**Ollama, running locally**

```yaml
Atwx\VectorSearch\Embedding\OpenAIEmbeddingsProvider:
  base_uri: 'http://localhost:11434/v1'
  api_key_env: 'OLLAMA_API_KEY'   # any non-empty value; Ollama ignores it
  model: 'nomic-embed-text'
  dimensions: 768
  batch_size: 1
```

### Using a different embeddings provider

Implement `Atwx\VectorSearch\Embedding\EmbeddingsProvider` and bind it with the Injector:

```php
use Atwx\VectorSearch\Embedding\EmbeddingsProvider;

class LocalEmbeddingsProvider implements EmbeddingsProvider
{
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        // Return one float[] per input text, in the same order.
    }
}
```

```yaml
SilverStripe\Core\Injector\Injector:
  Atwx\VectorSearch\Embedding\EmbeddingsProvider:
    class: App\Embedding\LocalEmbeddingsProvider
```

## Supported databases

Only MariaDB is supported at the moment. To support another database, add SQL function wrappers in `Atwx\VectorSearch\ORM\Func\Generic` (subclasses of `Func`) that map to that database's vector functions.

## Example project

[silverstripe-embed-demo](https://github.com/atwx/silverstripe-embed-demo) imports the Silverstripe developer docs and answers questions about them with vector search.

## Contributors

| [![Sebi](https://github.com/ItsSebis.png?size=100)](https://github.com/ItsSebis) | [![Apilonius64](https://github.com/Apilonius64.png?size=100)](https://github.com/Apilonius64) |
| :------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------------: |
| [Sebi](https://github.com/ItsSebis)                                              | [Apilonius64](https://github.com/Apilonius64)                                                 |

See also [silverstripe-vector-showcase](https://github.com/atwx/silverstripe-vector-showcase).

# S2 Embed

Vector embeddings and similarity search for the Silverstripe ORM, using MariaDB's native `VECTOR` type.

S2 Embed adds a `Vector` field type, turns text into embeddings through a pluggable provider (OpenRouter by default), and orders `DataList` by vector distance. It is a good fit for semantic search, "related content", and retrieval for RAG setups.

## Requirements

- Silverstripe Framework 6
- MariaDB 11.7 or newer (the first release with `VECTOR` columns and vector indexes)
- An [OpenRouter](https://openrouter.ai) API key, or your own embeddings provider (see [Using a different embeddings provider](#using-a-different-embeddings-provider))

## Installation

```bash
composer require atwx/s2embed
```

Add your API key to `.env`:

```dotenv
OPENROUTER_API_KEY="sk-or-..."
```

## Usage

### 1. Add a vector field

```php
use SilverStripe\ORM\DataObject;

class Document extends DataObject
{
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
use Atwx\ORM\Func\VectorFrom;

$doc = Document::create();
$doc->Content = $text;
$doc->Embedding = VectorFrom::single($text)->compile();
$doc->write();
```

The column is `NOT NULL`, so every record needs an embedding before it is written.

To embed many texts with one API call, use `VectorFrom::batch()`. It returns one `VectorFrom` per input, in the same order:

```php
$vectors = VectorFrom::batch($texts);

foreach ($texts as $i => $text) {
    $doc = Document::create();
    $doc->Content = $text;
    $doc->Embedding = $vectors[$i]->compile();
    $doc->write();
}
```

### 3. Search by similarity

```php
use Atwx\ORM\Helpers\VSearch;

$results = VSearch::sort_by_distance(Document::get(), 'Embedding', $userInput)->limit(5);

foreach ($results as $doc) {
    echo $doc->embedding_distance; // lower is more similar
}
```

`sort_by_distance()` embeds the search text, adds the distance to each row as `embedding_distance`, and sorts ascending. Pass a sort direction and an annotation name as the 4th and 5th arguments to change either.

### Custom distance queries

`VSearch::annotate_query()` adds any SQL function expression to the query as a named field. The distance functions live in `Atwx\ORM\Func\Distance`:

| Class                  | SQL function              |
| ---------------------- | ------------------------- |
| `VecDistance`          | `VEC_DISTANCE` (uses the index's metric) |
| `VecDistanceCosine`    | `VEC_DISTANCE_COSINE`     |
| `VecDistanceEuclidean` | `VEC_DISTANCE_EUCLIDEAN`  |

```php
use Atwx\ORM\Func\Column;
use Atwx\ORM\Func\Distance\VecDistanceEuclidean;
use Atwx\ORM\Func\VectorFrom;
use Atwx\ORM\Helpers\VSearch;

$list = VSearch::annotate_query(
    Document::get(),
    'distance',
    new VecDistanceEuclidean(new Column('Embedding'), VectorFrom::single($userInput))
)->sort('distance', 'ASC');
```

## Configuration

The default provider and model are set in [`_config/embeddings.yml`](_config/embeddings.yml). Override them in your project's config:

```yaml
Atwx\Embedding\OpenRouterEmbeddingsProvider:
  model: 'openai/text-embedding-3-small'
  dimensions: 1536
```

If you switch to a model with a different output size, update the `Vector(<size>)` field to match. MariaDB will reject vectors whose length doesn't match the column.

### Using a different embeddings provider

Implement `Atwx\Embedding\EmbeddingsProvider` and bind it with the Injector:

```php
use Atwx\Embedding\EmbeddingsProvider;

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
  Atwx\Embedding\EmbeddingsProvider:
    class: App\Embedding\LocalEmbeddingsProvider
```

## Supported databases

Only MariaDB is supported at the moment. To support another database, add SQL function wrappers in `Atwx\ORM\Func` (subclasses of `Func`) that map to that database's vector functions.

## Example project

[silverstripe-embed-demo](https://github.com/atwx/silverstripe-embed-demo) imports the Silverstripe developer docs and answers questions about them with vector search.

## Contributors

| [![Sebi](https://github.com/ItsSebis.png?size=100)](https://github.com/ItsSebis) | [![Apilonius64](https://github.com/Apilonius64.png?size=100)](https://github.com/Apilonius64) |
| :------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------------------: |
| [Sebi](https://github.com/ItsSebis)                                              | [Apilonius64](https://github.com/Apilonius64)                                                 |

See also [silverstripe-vector-showcase](https://github.com/atwx/silverstripe-vector-showcase).
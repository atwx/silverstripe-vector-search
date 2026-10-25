# S2 Embed

Adds support for vector embeddings to you mysql database.

## Qickstart

### Configuration

You will need to specify your `OPENROUTER_API_KEY` in `.env`. For further configuration see e.g. the `embeddings.yml` file.

### Basic usage
You can now create fields of type `Vector`. Example:

```php
class Document extends DataObject {
    private static $class_description = 'Document';
    private static $table_name = 'document';

    private static $db = [
        'Content' => 'Text',
        'Embedding' => 'Vector',
    ];

    private static array $indices = [
        'Embedding' => true
    ];
}
```

Assign a value:
```php
$doc->Embedding = VectorFrom::single($your_text)->compile()
``` 

Search for similar texts:
```php
$q = VSearch::sort_by_distance(Document::get(), 'Embedding', $user_input)->limit(5);
```

## Supported databases
Currently, MariaDB is officially supported. You may write custom SQL Func Wrappers (see Atwx\ORM\Func) to add support for another database.

## Contributers

This project has been made possible by

| [![Sebi](https://github.com/ItsSebis.png?size=100)](https://github.com/ItsSebis) | [![Apilonius64](https://github.com/Apilonius64.png?size=100)](https://github.com/Apilonius64) |
| --------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| [Sebi](https://github.com/Apilonius64)                                                 | [Apilonius64](https://github.com/Apilonius64)                                                 |

See also: https://github.com/atwx/silverstripe-vector-showcase
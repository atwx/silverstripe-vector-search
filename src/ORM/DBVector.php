<?php

namespace Atwx\VectorSearch\ORM;

use Atwx\VectorSearch\Embedding\EmbeddingsProvider;
use Override;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DB;

class DBVector extends DBField
{
    private string $distance;
    protected int $size;

    #[Override]
    public function scalarValueOnly(): bool
    {
        return false;
    }

    public function __construct(
        ?string $name = null,
        string $distance = "euclidean",
        int | null $size = null,
        array $options = []
    ) {
        $this->size = $size ?? Injector::inst()->get(EmbeddingsProvider::class)->getDimensions();

        $this->distance = $distance;

        parent::__construct($name, $options);
    }

    public function getFieldSpec(): string
    {
        return "vector({$this->size}) not null";
    }

    #[Override]
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->value === null) {
            $result->addFieldError(
                'Vector',
                'NULL is not supported: MariaDB does not allow NULL in a VECTOR INDEX.'
            );

            return $result;
        }

        if (!is_array($this->value)) {
            $result->addFieldError(
                'Vector',
                'DBVector expects a compiled function as value. '
                    . 'Use VectorFrom::single(...)->compile(), or assign VectorFrom::single(...) '
                    . 'to a DataObject that uses the SupportsVFuncs trait.'
            );
        }

        return $result;
    }

    public function writeToManipulation(array &$manipulation): void
    {
        $arr = array_merge($this->value); // copy

        $sql = array_shift($arr);
        $params = $arr;

        $manipulation['fields'][$this->name] = [$sql => $params];
    }

    #[Override]
    public function getIndexSpecs(): ?array
    {
        return [
            'type' => 'vector',
            'columns' => [$this->name],
            'distance' => $this->distance
        ];
    }

    public function requireField(): void
    {
        DB::require_field($this->tableName, $this->name, $this->getFieldSpec());
    }
}

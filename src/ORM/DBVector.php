<?php

namespace Atwx\VectorSearch\ORM;

use Atwx\VectorSearch\Embedding\EmbeddingsProvider;
use Atwx\VectorSearch\ORM\Func\VectorFrom;
use InvalidArgumentException;
use Override;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FormField;
use SilverStripe\Model\ModelData;
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
        int $m = 16,
        array $options = []
    ) {
        $this->size = $size ?? Injector::inst()->get(EmbeddingsProvider::class)->getDimensions();
        $this->m = $m;

        assert(in_array($distance, ["cosine", "euclidean"]));

        $this->distance = $distance;

        parent::__construct($name, $options);
    }

    #[Override]
    public function setValue(mixed $value, null|array|ModelData $record = null, bool $markChanged = true): static
    {
        $this->value = match (true) {
                $value === null              => null,
                $value instanceof VectorFrom => $value->compile(),
                (is_array($value) && count($value) > 0 && is_float($value[0]))  => array_map('floatval', array_values($value)),
                (is_array($value) && count($value) > 0 && is_string($value[0])) => $value,

                is_string($value)            => array_values(unpack('g*', $value)),
                default                      => throw new InvalidArgumentException("Unsupported value for vector field {$this->name}"),
        };

        return $this;
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
        if (!is_string($this->value[0])) {
            $arr = VectorFrom::create($this->value)->compile();
        }
        else {
            $arr = array_merge($this->value); // copy
        }

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
            'distance' => $this->distance,
            'm' => $this->m
        ];
    }

    public function requireField(): void
    {
        DB::require_field($this->tableName, $this->name, $this->getFieldSpec());
    }

    #[Override]
    public function scaffoldFormField(?string $title = null, array $params = []): ?FormField
    {
        return null;
    }

    #[Override]
    public function scaffoldSearchField(?string $title = null): ?FormField
    {
        return null;
    }
}

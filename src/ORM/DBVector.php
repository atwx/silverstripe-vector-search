<?php

namespace Atwx\VectorSearch\ORM;

use Override;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DB;

class DBVector extends DBField
{
    private string $distance = 'cosine';
    protected int $size;

    #[Override]
    public function scalarValueOnly(): bool
    {
        return false;
    }

    public function __construct(?string $name = null, int $size = 1536, array $options = [])
    {
        $this->size = $size;

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

        if ($this->value === NULL) {
            $result->addFieldError(
                'Vector',
                'NULL is currently not supported. Note: E.g. MariaDB does not support NULL for VECTOR INDEX at all.'
            );

            return $result;
        }

        if (!is_array($this->value)) {
            $result->addFieldError(
                'Vector',
                'DBVector expects a compiled Function as value. You likely want to use VectorFrom::single(...)->compile()'
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
            'columns' => [$this->name], # clean?
            'distance' => $this->distance
        ];
    }

    public function requireField(): void
    {
        DB::require_field($this->tableName, $this->name, $this->getFieldSpec());
    }
}

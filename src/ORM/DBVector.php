<?php

namespace Atwx\VectorSearch\ORM;

use InvalidArgumentException;
use Override;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DB;

class DBVector extends DBField
{
    private string $distance = 'cosine';

    #[Override]
    public function scalarValueOnly(): bool
    {
        return false;
    }

    public function __construct(?string $name = null, int $size = 1536, array $options = [])
    {
        $this->size = $size;

        return parent::__construct($name, $options);
    }

    public function setValue($value, $record = null, $markChanged = true): static
    {
        if ($value !== null && !is_array($value)) {
            throw new InvalidArgumentException(
                'DBVector expects a compiled Function as value. You likely want to use VectorFrom::single(...)->compile()'
            );
        }

        return parent::setValue($value, $record, $markChanged);
    }


    public function getFieldSpec(): string
    {
        return "vector({$this->size}) not null";
    }

    public function writeToManipulation(array &$manipulation): void
    {
        $existent = ($this->value !== null);
        $sql = array_shift($this->value);
        $params = $this->value;

        $manipulation['fields'][$this->name] = $existent
            ? [$sql => $params]
            : $this->nullValue();
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

<?php

namespace App\ORM;

use Override;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DB;

class DBVector extends DBField
{
    # TODO: abstract this
    private string $distance = 'cosine';

    # #[Override]
    public function __construct(?string $name = null, array $options = [])
    {
        $this->size = $options[0];

        die($size);

        return parent::__construct($name, $options);
    }

    public function getFieldSpec(): string
    {
        return 'VECTOR({$this->size}) NOT NULL, '
            . 'VECTOR INDEX (Embedding) DISTANCE=cosine';
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

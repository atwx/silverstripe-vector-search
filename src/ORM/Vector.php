<?php

namespace Atwx\ORM;

use Atwx\ORM\Func\Func;
use Override;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DB;
use SilverStripe\Core\Injector\Injector;

class DBVector extends DBField
{
    # TODO: abstract this
    private string $distance = 'cosine';

    #[Override]
    public function scalarValueOnly(): bool
    {
        return false;
    }

    # #[Override]
    public function __construct(?string $name = null, int $size = 1536, array $options = [])
    {
        $this->size = $size;

        return parent::__construct($name, $options);
    }

    public function setValue($value, $record = null, $markChanged = true): static
    {
        # TODO: Possibly autogen embeddings from Text, Possibly extra field which creates two db field
        # and returns an e and e.source (-> extra class)
        if ($value !== null && !is_array($value)) {
            throw new InvalidArgumentException(
                'DBVector expects an array'
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
        $manipulation['fields'][$this->name] = $this->value
            ? [Func::compile_obj($this->value) => []]
            : $this->nullValue();
    }

    # TODO: Read manipulation
    # TODO: inspect sql
    # 15

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

    // public function scaffoldFormField(?string $title = null, array $params = []): ?FormField
    // {
    //     // A raw vector isn't sensibly editable as a form field - no CMS UI for this column.
    //     return null;
    // }
}

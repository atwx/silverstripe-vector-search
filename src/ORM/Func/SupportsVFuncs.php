<?php

namespace Atwx\VectorSearch\ORM\Func;

use Atwx\VectorSearch\ORM\DBVector;
use SilverStripe\Core\Injector\Injector;

trait SupportsVFuncs
{
    /**
     * Whether a given field is a vector, keyed by "<class>.<field>". Resolving the spec
     * means building the DBField, so the answer is kept rather than the object.
     *
     * @var array<string, bool>
     */
    private static array $is_vector_field = [];

    public function setField(string $fieldName, mixed $value): static
    {
        if ($value instanceof Func) {
            $value = $value->compile();
        }

        return parent::setField($fieldName, $value);
    }

    /**
     * DataObject::getField() hands back whatever sits in the record, and for a field that
     * was hydrated from the database that is the raw column: a string of packed floats,
     * never passed through DBVector::setValue(). Decode those, so reading a vector back
     * gives the same array that was written.
     */
    public function getField(string $field): mixed
    {
        $value = parent::getField($field);

        if (!is_string($value) || !static::isVectorField($field)) {
            return $value;
        }

        return $this->dbObject($field)->getValue();
    }

    private static function isVectorField(string $field): bool
    {
        $key = static::class . '.' . $field;

        if (!isset(self::$is_vector_field[$key])) {
            $spec = static::getSchema()->fieldSpec(static::class, $field);

            self::$is_vector_field[$key] = $spec !== null
                && Injector::inst()->create($spec, $field) instanceof DBVector;
        }

        return self::$is_vector_field[$key];
    }
}

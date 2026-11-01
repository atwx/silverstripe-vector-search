<?php

namespace Atwx\VectorSearch\ORM\Func;

trait SupportsVFuncs
{
    public function setField(string $fieldName, mixed $value): static
    {
        if ($value instanceof Func) {
            $value = $value->compile();
        }

        return parent::setField($fieldName, $value);
    }
}

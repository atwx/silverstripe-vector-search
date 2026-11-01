<?php

namespace Atwx\VectorSearch\ORM\Func;

abstract class Column extends Func
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }
}

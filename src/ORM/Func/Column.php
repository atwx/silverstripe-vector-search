<?php

namespace Atwx\ORM\Func;

use SilverStripe\ORM\DB;

class Column extends Func {
    public function __construct(string $name) {
        parent::__construct($name);
    }

    public function compile(): array {
        return [
            DB::get_conn()->escapeIdentifier($this->args[0])
        ];
    }
}
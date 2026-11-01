<?php

namespace Atwx\VectorSearch\ORM\Func\Backend\Generic;

use Atwx\VectorSearch\ORM\Func\Column;
use SilverStripe\ORM\DB;

class GColumn extends Column
{
    public function compile(): array
    {
        return [
            DB::get_conn()->escapeIdentifier($this->args[0])
        ];
    }
}

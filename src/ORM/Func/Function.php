<?php

namespace Atwx\ORM\Func;

use SilverStripe\Core\Convert;

class Func {
    public string $function = None;
    public string $template = "%s (%s)";
    public string $arg_seperator = ",";

    public function __construct()
    {
        $this->num_args = func_num_args();
        $this->args = func_get_args();
    }

    // Compiles a single obj
    public static function compile_obj(string | Func $o): string {
        if ($o instanceof Func) {
            return $o->compile();
        }

        return Convert::raw2sql($o, true);
    }

    protected function compile_args(): string {
        $compiled_args = [];

        for ($i=0; $i != $this->num_args; $i++) {
            array_push($compiled_args, $this->compile_obj($this->args[$i]));
        }

        return join($this->arg_seperator, $compiled_args);
    }

    public function compile(): string {
        return sprintf($this->template, $this->function, $this->compile_args());
    }
}

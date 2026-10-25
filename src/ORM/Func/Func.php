<?php

namespace Atwx\ORM\Func;

use SilverStripe\Core\Convert;

class Func {
    public string $function = '';
    public int $num_args = 0;
    public array $args = [];
    public string $template = "%s(%s)";
    public string $arg_seperator = ",";

    public function __construct()
    {
        $this->num_args = func_num_args();
        $this->args = func_get_args();
    }

    // array layout:
    // [template, arg0, arg1, ...]

    // Compiles a single obj
    public static function compile_obj(string | Func $o): array {
        if ($o instanceof Func) {
            return $o->compile();
        }

        return ['?', $o];
    }

    protected function compile_args(): array {
        $compiled_sql = [];
        $compiled_args = [];

        for ($i=0; $i != $this->num_args; $i++) {
            $result = $this->compile_obj($this->args[$i]);
            array_push($compiled_sql, array_shift($result));
            array_push($compiled_args, ...$result);
        }

        $compiled = [join($this->arg_seperator, $compiled_sql)];
        return array_merge($compiled, $compiled_args);
    }

    public function compile(): array {
        $result = $this->compile_args();
        $compiled = [sprintf($this->template, $this->function, array_shift($result))];

        return array_merge($compiled, $result);
    }
}

<?php

namespace Atwx\VectorSearch\ORM\Func;

use SilverStripe\Core\Injector\Injector;

class Func {
    public string $function = '';
    public int $num_args = 0;
    public array $args = [];
    public string $template = "%s(%s)";
    public string $argSeparator = ",";

    public function __construct()
    {
        $this->num_args = func_num_args();
        $this->args = func_get_args();
    }

    // array layout:
    // [template, arg0, arg1, ...]

    // Compiles a single obj
    public static function compileObj(string | Func $o): array {
        if ($o instanceof Func) {
            return $o->compile();
        }

        return ['?', $o];
    }

    protected function compileArgs(): array {
        $compiledSQL = [];
        $compiledArgs = [];

        for ($i=0; $i != $this->num_args; $i++) {
            $result = $this->compileObj($this->args[$i]);
            array_push($compiledSQL, array_shift($result));
            array_push($compiledArgs, ...$result);
        }

        $compiled = [join($this->argSeparator, $compiledSQL)];
        return array_merge($compiled, $compiledArgs);
    }

    public function compile(): array {
        $result = $this->compileArgs();
        $compiled = [sprintf($this->template, $this->function, array_shift($result))];

        return array_merge($compiled, $result);
    }

    public static function create(mixed ...$args) {
        # R: works?
        return Injector::inst()->create(static::class, ...$args);
    }
}

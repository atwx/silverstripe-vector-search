<?php

namespace Atwx\VectorSearch\ORM\Func;

use SilverStripe\Core\Injector\Injector;

class Func
{
    protected string $function = '';
    protected int $num_args = 0;
    protected array $args = [];
    protected string $template = "%s(%s)";
    protected string $argSeparator = ",";

    public function __construct()
    {
        $this->num_args = func_num_args();
        $this->args = func_get_args();
    }

    /**
     * Compiles one argument. A compiled fragment is [$sql, ...$parameters]: the SQL
     * first, then one parameter per `?` in it, left to right. Anything that is not a
     * Func becomes a single bound parameter.
     */
    public static function compileObj(string | Func $o): array
    {
        if ($o instanceof Func) {
            return $o->compile();
        }

        return ['?', $o];
    }

    protected function compileArgs(): array
    {
        $compiledSQL = [];
        $compiledArgs = [];

        for ($i = 0; $i != $this->num_args; $i++) {
            $result = $this->compileObj($this->args[$i]);
            array_push($compiledSQL, array_shift($result));
            array_push($compiledArgs, ...$result);
        }

        $compiled = [join($this->argSeparator, $compiledSQL)];
        return array_merge($compiled, $compiledArgs);
    }

    public function compile(): array
    {
        $result = $this->compileArgs();
        $compiled = [sprintf($this->template, $this->function, array_shift($result))];

        return array_merge($compiled, $result);
    }

    public static function create(mixed ...$args)
    {
        return Injector::inst()->create(static::class, ...$args);
    }
}

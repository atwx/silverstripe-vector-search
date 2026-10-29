<?php

namespace Atwx\ORM\Func\Generic;

use Atwx\Embedding\EmbeddingsProvider;
use Override;
use SilverStripe\Core\Injector\Injector;

class VectorFrom extends Func {
    public string $function = 'VEC_FromText';

    public function __construct(array $vec) {
        parent::__construct($vec);
    }

    public static function single(string $text): VectorFrom {
        $embeddings = Injector::inst()->get(EmbeddingsProvider::class);

        return new VectorFrom($embeddings->embed($text));
    }

    public static function batch(array $texts): array {
        $embeddings = Injector::inst()->get(EmbeddingsProvider::class);

        return array_map(
            function ($item) {
                return new VectorFrom($item);
            },
            $embeddings->embedBatch($texts)
        );
    }

    #[Override]
    protected function compile_args(): array
    {
        return ['?', json_encode($this->args[0])];
    }
}

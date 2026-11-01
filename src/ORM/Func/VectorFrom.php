<?php

namespace Atwx\VectorSearch\ORM\Func;

use Atwx\VectorSearch\ORM\Func\Func;

use Atwx\VectorSearch\Embedding\EmbeddingsProvider;
use SilverStripe\Core\Injector\Injector;
use Override;

abstract class VectorFrom extends Func {
    public function __construct(array $vec) {
        parent::__construct($vec);
    }

    public static function single(string $text): VectorFrom {
        $embeddings = Injector::inst()->get(EmbeddingsProvider::class);

        return Injector::inst()->create(VectorFrom::class, $embeddings->embed($text));
    }

    public static function batch(array $texts): array {
        $embeddings = Injector::inst()->get(EmbeddingsProvider::class);

        return array_map(
            function ($item) {
                return Injector::inst()->create(VectorFrom::class, $item);
            },
            $embeddings->embedBatch($texts)
        );
    }

    #[Override]
    protected function compileArgs(): array
    {
        return ['?', json_encode($this->args[0])];
    }
}

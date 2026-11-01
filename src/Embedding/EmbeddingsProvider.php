<?php

namespace Atwx\VectorSearch\Embedding;

/**
 * Turns text into an embedding vector. To use a different provider (a local model,
 * a different API), write a class implementing this interface and point the Injector
 * binding in your project's config at it — nothing else needs to change.
 * 
 * @author Sebi <https://github.com/ItsSebis>
 */
interface EmbeddingsProvider
{
    /** @return float[] */
    public function embed(string $text): array;

    /** @param string[] $texts @return float[][] one vector per input, same order */
    public function embedBatch(array $texts): array;

    public function getDimensions(): int;
}

<?php

namespace Atwx\Embedding;

// Original author: Sebi

/**
 * Turns text into an embedding vector. To use a different provider (a local model,
 * a different API), write a class implementing this interface and point the Injector
 * binding in app/_config/vector-showcase.yml at it — nothing else needs to change.
 */
interface EmbeddingsProvider
{
    /** @return float[] */
    public function embed(string $text): array;

    /** @param string[] $texts @return float[][] one vector per input, same order */
    public function embedBatch(array $texts): array;
}

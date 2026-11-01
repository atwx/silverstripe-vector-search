<?php

namespace Atwx\VectorSearch\Tests\Stub;

use Atwx\VectorSearch\Embedding\EmbeddingsProvider;
use SilverStripe\Dev\TestOnly;

/**
 * Stands in for a real embeddings API. It never makes a request: a vector is either
 * registered up front with setVector(), or derived from the text so that the same text
 * always yields the same vector.
 */
class StubEmbeddingsProvider implements EmbeddingsProvider, TestOnly
{
    /**
     * Every text passed to embed(), in call order.
     *
     * @var string[]
     */
    public array $embedCalls = [];

    /**
     * Every batch passed to embedBatch(), in call order.
     *
     * @var string[][]
     */
    public array $embedBatchCalls = [];

    /** @var array<string, float[]> */
    private array $vectors = [];

    public function __construct(private int $dimensions = 3)
    {
    }

    /**
     * @param float[] $vector
     */
    public function setVector(string $text, array $vector): static
    {
        $this->vectors[$text] = $vector;

        return $this;
    }

    public function setDimensions(int $dimensions): static
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    public function embed(string $text): array
    {
        $this->embedCalls[] = $text;

        return $this->vectorFor($text);
    }

    public function embedBatch(array $texts): array
    {
        $texts = array_values($texts);
        $this->embedBatchCalls[] = $texts;

        return array_map(fn (string $text): array => $this->vectorFor($text), $texts);
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * @return float[]
     */
    private function vectorFor(string $text): array
    {
        if (isset($this->vectors[$text])) {
            return $this->vectors[$text];
        }

        $seed = crc32($text);
        $vector = [];

        for ($i = 0; $i < $this->dimensions; $i++) {
            $vector[] = round((($seed >> $i) % 1000) / 1000, 3);
        }

        return $vector;
    }
}

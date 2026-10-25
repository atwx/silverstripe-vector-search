<?php

namespace Atwx\Embedding;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

/**
 * Default EmbeddingsProvider: calls OpenRouter's OpenAI-compatible /embeddings endpoint.
 *
 * Requires OPENROUTER_API_KEY in .env — never hardcode the key here.
 * `model`/`dimensions` are set via _config/embeddings.yml so they can be changed
 * without touching this file. If you change `model` to one with a different output size,
 * update `dimensions` here AND the `Vector(<size>)` column on your DataObject to match,
 * or writes will fail (the VECTOR column has a fixed size).
 * 
 * @author Sebi <https://github.com/ItsSebis>
 */
class OpenRouterEmbeddingsProvider implements EmbeddingsProvider
{
    use Configurable;

    private static string $model = 'openai/text-embedding-3-small';

    private static int $dimensions = 1536;

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $apiKey = Environment::getEnv('OPENROUTER_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('OPENROUTER_API_KEY is not set in .env');
        }

        $ch = curl_init('https://openrouter.ai/api/v1/embeddings');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => static::config()->get('model'),
                // OpenRouter's /embeddings endpoint accepts a batch of strings in one call.
                'input' => $texts,
            ]),
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($response === false) {
            throw new \RuntimeException("OpenRouter request failed: $error");
        }

        $data = json_decode($response, true);
        $embeddings = array_column($data['data'] ?? [], 'embedding');
        if (count($embeddings) !== count($texts)) {
            throw new \RuntimeException("Unexpected OpenRouter response: $response");
        }

        return $embeddings;
    }
}

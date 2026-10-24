<?php

namespace App\Actions;

use Laravel\Ai\Embeddings;
use RuntimeException;

class GenerateSearchEmbedding
{
    public function ready(): bool
    {
        $provider = config('semantic-search.provider');

        return (bool) config('semantic-search.enabled') && filled($provider) && filled(config('semantic-search.model'))
            && (Embeddings::isFaked() || filled(config("ai.providers.{$provider}.key")));
    }

    public function profile(): string
    {
        return hash('sha256', json_encode(['catalog-v1', config('semantic-search.provider'), config('semantic-search.model'), config('semantic-search.dimensions')], JSON_THROW_ON_ERROR));
    }

    /** @return list<float> */
    public function handle(string $text): array
    {
        if (! $this->ready()) {
            throw new RuntimeException('Configura y activa el proveedor de embeddings antes de indexar.');
        }
        $dimensions = (int) config('semantic-search.dimensions');
        if ($dimensions < 1 || $dimensions > 16000) {
            throw new RuntimeException('Las dimensiones del embedding no son válidas.');
        }
        $embedding = Embeddings::for([$text])->dimensions($dimensions)->timeout(10)
            ->generate(config('semantic-search.provider'), config('semantic-search.model'))->embeddings[0];
        if (count($embedding) !== $dimensions || ! array_is_list($embedding)) {
            throw new RuntimeException('El proveedor devolvió un embedding de dimensiones incorrectas.');
        }
        $magnitude = 0.0;
        foreach ($embedding as $number) {
            if (! is_finite($number)) {
                throw new RuntimeException('El embedding contiene valores no válidos.');
            }
            $magnitude += $number * $number;
        }
        if ($magnitude === 0.0) {
            throw new RuntimeException('El proveedor devolvió un embedding vacío.');
        }

        return array_map(floatval(...), $embedding);
    }
}

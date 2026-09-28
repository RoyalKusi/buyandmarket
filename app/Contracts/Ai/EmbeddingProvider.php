<?php

namespace App\Contracts\Ai;

interface EmbeddingProvider
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}

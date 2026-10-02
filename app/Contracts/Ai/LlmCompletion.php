<?php

namespace App\Contracts\Ai;

/**
 * @param  list<array{id: string, name: string, arguments: array}>  $toolCalls
 */
final readonly class LlmCompletion
{
    public function __construct(
        public ?string $content,
        public array $toolCalls = [],
    ) {}
}

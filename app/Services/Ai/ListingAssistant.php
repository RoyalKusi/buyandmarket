<?php

namespace App\Services\Ai;

use App\Contracts\Ai\LlmProvider;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * TDD §5.6/§4.2 seller journey: "LLM prompted with seller-supplied photos
 * + short bullet input -> draft title/description/bullet specs ...
 * seller reviews/edits rather than starting blank." No product-image
 * pipeline exists yet (§5.8, flagged deferred since Run 1.8), so this is
 * the text-only half: bullets in, a description suggestion out. The
 * seller's own title is never touched — only description is AI-drafted,
 * kept deliberately narrow rather than guessing at a title a real
 * catalogue-quality bar would need photos to get right.
 */
class ListingAssistant
{
    public function __construct(
        private readonly LlmProvider $llm,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function suggestDescription(Product $product, string $bullets, User $seller): string
    {
        $completion = $this->llm->complete([
            [
                'role' => 'system',
                'content' => 'You write concise, accurate marketplace product descriptions (2-4 sentences) from a seller\'s short bullet points. Never invent specifications the seller did not mention.',
            ],
            [
                'role' => 'user',
                'content' => "Product title: {$product->title}\nSeller's notes:\n{$bullets}",
            ],
        ]);

        $suggestion = trim((string) $completion->content);

        $product->update(['ai_suggested_description' => $suggestion]);

        // TDD §5.7: "every generated-content acceptance" is audited —
        // the generation itself is logged too, so a later Accept/Discard
        // has a paired proposal on record.
        $this->auditLogger->log(
            actor: $seller,
            action: 'ai.suggestion.generated',
            subject: $product,
            before: null,
            after: ['bullets' => $bullets, 'suggestion' => $suggestion],
        );

        return $suggestion;
    }

    public function acceptDescription(Product $product, User $seller): Product
    {
        $suggestion = $product->ai_suggested_description;
        $before = ['description' => $product->description];

        $product->update(['description' => $suggestion, 'ai_suggested_description' => null]);

        $this->auditLogger->log(
            actor: $seller,
            action: 'ai.suggestion.accepted',
            subject: $product,
            before: $before,
            after: ['description' => $suggestion],
        );

        return $product;
    }

    public function discardDescription(Product $product, User $seller): Product
    {
        $product->update(['ai_suggested_description' => null]);

        $this->auditLogger->log(
            actor: $seller,
            action: 'ai.suggestion.discarded',
            subject: $product,
        );

        return $product;
    }
}

<?php

namespace App\Services\Ai;

use App\Contracts\Ai\LlmProvider;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    /**
     * TDD §5.8's alt-text step, scoped honestly per docs/adr/0007: seeded
     * from catalogue data (title, category, attribute values already on
     * the product), not from the image's actual pixels — no vision-
     * capable provider is wired in. Applied directly to the image rather
     * than staged for Accept/Discard like the description suggestion:
     * an alt-text string is a low-stakes accessibility label, not
     * customer-facing marketing copy, so the TDD §5.7 "never auto-
     * applied" guardrail is reserved for content that actually
     * represents the seller's claims about the product.
     */
    public function suggestAltText(Product $product): string
    {
        $attributeValues = $product->variants->flatMap->attributeValues->unique('id')->pluck('value')->implode(', ');

        $completion = $this->llm->complete([
            [
                'role' => 'system',
                'content' => 'Write a single, concise (under 125 characters) image alt-text description for an e-commerce product photo, for screen readers. No marketing language, just what the photo most likely shows.',
            ],
            [
                'role' => 'user',
                'content' => "Product: {$product->title}\nCategory: {$product->category?->name}\nKnown attributes: {$attributeValues}",
            ],
        ]);

        return trim((string) $completion->content, " \t\n\r\0\x0B\"");
    }

    /**
     * TDD §5.6 "suggest a category from a photo, extract colour/material"
     * — scoped per docs/adr/0007 to a text-seeded version: the title and
     * the seller's own bullet notes, matched against the real leaf-
     * category/attribute catalogue (never an invented one). The LLM is
     * asked to answer in a fixed two-line format so the result can be
     * parsed deterministically and matched to real IDs — nothing here
     * trusts free-text well enough to invent a category or attribute
     * value that doesn't already exist in the catalogue.
     *
     * Not audited (unlike suggestDescription()): nothing is written yet
     * — this only returns a preview for the seller to apply themselves
     * on the still-unsaved product-creation form, so TDD §8.9's "every
     * privileged mutation" scope doesn't apply until the form is
     * actually submitted.
     *
     * @param  Collection<int, Category>  $leafCategories
     * @return array{category: ?Category, attributes: array<int, array{attribute_id: int, attribute_name: string, value_id: int, value: string}>}
     */
    public function suggestCategorization(string $title, string $bullets, Collection $leafCategories): array
    {
        $catalogue = $leafCategories->map(function (Category $category) {
            $attributeNames = $category->attributes->pluck('name')->implode(', ');

            return "- {$category->name}".($attributeNames ? " (attributes: {$attributeNames})" : '');
        })->implode("\n");

        $completion = $this->llm->complete([
            [
                'role' => 'system',
                'content' => "You match a product to exactly one category from a fixed list, and suggest values for that category's listed attributes. Respond in exactly this format, nothing else:\nCategory: <exact name from the list>\nAttributes: <attribute>=<value>; <attribute>=<value>\nOnly use attributes/values that plausibly apply; if none apply, write \"Attributes: none\".",
            ],
            [
                'role' => 'user',
                'content' => "Categories:\n{$catalogue}\n\nProduct title: {$title}\nSeller's notes:\n{$bullets}",
            ],
        ]);

        return $this->parseCategorization((string) $completion->content, $leafCategories);
    }

    /**
     * @param  Collection<int, Category>  $leafCategories
     * @return array{category: ?Category, attributes: array<int, array{attribute_id: int, attribute_name: string, value_id: int, value: string}>}
     */
    private function parseCategorization(string $raw, Collection $leafCategories): array
    {
        $categoryLine = Str::of($raw)->after('Category:')->before("\n")->trim();
        $category = $leafCategories->first(fn (Category $c) => Str::lower($c->name) === Str::lower($categoryLine));

        $suggestedAttributes = [];

        if ($category !== null) {
            $attributesLine = Str::of($raw)->after('Attributes:')->trim();

            foreach (explode(';', (string) $attributesLine) as $pair) {
                if (! str_contains($pair, '=')) {
                    continue;
                }

                [$attributeName, $valueName] = array_map('trim', explode('=', $pair, 2));

                $attribute = $category->attributes->first(fn ($a) => Str::lower($a->name) === Str::lower($attributeName));
                $value = $attribute?->values->first(fn ($v) => Str::lower($v->value) === Str::lower($valueName));

                if ($attribute !== null && $value !== null) {
                    $suggestedAttributes[] = [
                        'attribute_id' => $attribute->id,
                        'attribute_name' => $attribute->name,
                        'value_id' => $value->id,
                        'value' => $value->value,
                    ];
                }
            }
        }

        return ['category' => $category, 'attributes' => $suggestedAttributes];
    }
}

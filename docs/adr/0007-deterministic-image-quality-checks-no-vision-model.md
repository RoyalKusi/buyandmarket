# 7. Deterministic image-quality checks instead of a vision model

Date: 2026-10-02
Status: Accepted

## Context

TDD §5.8 describes the product-image pipeline as doing "validation,
quality scoring, background processing, compression/variants, alt-text"
on every upload, and §5.6 separately describes AI suggestions that read
a product's photo to suggest its category or attributes (colour,
material). Both readings assume a vision-capable model is available to
actually look at the image.

This build's AI boundary (`App\Contracts\Ai\LlmProvider`/
`EmbeddingProvider`, Run 1.8) is deliberately provider-agnostic but
chat-completion-shaped — `complete(array $messages, array $tools)` takes
text messages, not image content, and the one concrete implementation
(`OpenAiCompatibleLlmProvider`) was never wired for multimodal input.
No sandbox network path to verify a vision call exists either (same
constraint `docs/adr/0006` and the Pesepay/Paynow adapters already
documented).

## Decision

The image pipeline's "quality scoring" (`App\Services\
ProductImageService`) is a deterministic set of checks against the
decoded image's own dimensions and file size — minimum resolution,
maximum aspect-ratio distortion, a sane file-size ceiling — not a
model's judgement call. Alt-text generation reuses the existing
text-only `LlmProvider` (`App\Services\Ai\ListingAssistant::
suggestAltText()`), seeded from the product's title, category and
attribute values rather than the image's actual visual content.

Category/attribute-extraction *from a photo* (TDD §5.6) stays out of
scope, same as it was flagged in Run 1.11's CHANGELOG — nothing here
changes that. What Run 1.16 adds instead is text-seeded category/
attribute *suggestions* (from the seller's title and bullet notes, the
same input `ListingAssistant::suggestDescription()` already takes), a
real but narrower capability than the TDD's photo-driven framing.

## Alternatives considered

- **Call a vision-capable chat completion endpoint directly**, bypassing
  the `LlmProvider` contract for this one feature. Rejected: it would
  reintroduce a vendor-specific code path the rest of the AI platform
  spent Run 1.8 deliberately avoiding, for a capability that can't be
  verified against a live API in this sandbox anyway (no network path
  — same constraint as every other external-API integration here).
- **Extend `LlmProvider::complete()` to accept image content** now, even
  unused. Rejected as speculative — TDD §9's build discipline says not
  to build for a hypothetical future request; the contract can grow an
  `image` parameter the day a provider implementation actually needs
  one, without touching `ToolExecutor`/`AssistantService` above it.
- **Skip alt-text entirely until vision exists.** Rejected: the TDD
  names alt-text as this pipeline's job, and a text-seeded description
  ("Bluetooth Speaker — waterproof, 10h battery" style alt text) is a
  genuine accessibility improvement over an empty `alt=""`, even if it
  isn't derived from the pixels themselves.

## Consequences

- "Quality scoring" rejects on resolution/aspect-ratio/size, not on
  blur, lighting, background clutter, or counterfeit-logo detection —
  the kind of check the TDD's phrasing ("quality scoring") could be read
  to imply. `ProductImageService` documents exactly which checks run, so
  nothing is silently implied beyond them.
- Alt-text can describe attributes the photo doesn't actually show
  (it's seeded from catalogue data, not pixels) — acceptable for a
  screen-reader label on a marketplace listing, not acceptable as a
  claim of visual analysis; nothing in the UI claims the alt text was
  "read from the image."
- The day a vision-capable provider is wired in, this ADR's boundary is
  exactly `ProductImageService`'s quality-check method and
  `ListingAssistant`'s alt-text/category-suggestion methods — the
  `product_images` schema, storage/variant pipeline, and every call site
  above them are unaffected.

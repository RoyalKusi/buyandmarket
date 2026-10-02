# 6. In-application vector search for the launch topology

Date: 2026-09-28
Status: Accepted

## Context

TDD §5.1's RAG pipeline and §6.2's `embeddings` table both name a
dedicated vector store as part of the architecture (the high-level
diagram in §1.3 draws it as its own box alongside MySQL and Redis), and
§13 stage 6 names "embedding volume/query latency exceeds the initial
vector store's comfortable range" as the trigger to migrate to a
dedicated vector database. But §2.4's actual launch topology (Hostinger
Cloud Startup, one app tier, one MySQL/MariaDB instance, Redis only
where the plan allows it) never provisions one — the same "boring now,
swappable later" shape the TDD already applies to `SearchProvider`
(MySQL full-text now, Meilisearch/Typesense later, §2.1) and `Storage`
(local disk now, S3-compatible later).

Run 1.8 needs a working retrieval step today, on this launch topology,
without inventing infrastructure the TDD itself defers to a later scale
stage.

## Decision

`embeddings.vector` is a plain MySQL `JSON` column (a serialized array
of floats), not a native vector type or an external index.
`App\Services\Ai\RetrievalService::search()` loads the metadata-filtered
candidate set into PHP and ranks it with an in-application cosine
similarity function — no reranking pass (§5.2's cross-encoder step is
also out of scope for the same reason: no dedicated retrieval
infrastructure to run one against).

This mirrors `SearchProvider`'s own split exactly: a `LlmProvider` /
`EmbeddingProvider` contract pair (`App\Contracts\Ai`) is the swap
boundary, with one concrete `OpenAiCompatibleLlmProvider` /
`OpenAiCompatibleEmbeddingProvider` implementation behind it — call
sites (`AssistantService`, `RetrievalService`, `IngestionService`) never
talk to a vendor's wire format directly, so a real vector database
(§13 stage 6) or a different LLM vendor is a new implementation bound in
`AppServiceProvider`, not a rewrite of the retrieval/tool-calling logic
above it.

## Alternatives considered

- **Stand up a vector database now** (pgvector, a managed vector store).
  Rejected: the TDD's own launch topology (§2.4) doesn't provision one,
  and adding infrastructure this run doesn't call for would be exactly
  the kind of unrequested scope the build directive says to flag rather
  than improvise past.
- **MySQL's native `VECTOR` type / an `ANN` index**, where the running
  MySQL version supports it. Rejected for this run specifically because
  the target is "MySQL/MariaDB on Hostinger Cloud Startup" (§2.1) — a
  specific engine/version isn't guaranteed, and this sandbox's own test
  database is SQLite (`phpunit.xml`), which has neither. A JSON column
  read back into PHP is the one representation guaranteed to work
  identically across every driver the app already has to support.
- **Skip ranking, return retrieval results unordered.** Rejected: TDD
  §5.3 rule 1 (grounded-or-declines) and rule 2 (structured fact
  injection) depend on the *most relevant* chunks actually reaching the
  model, not an arbitrary subset — an unranked "first N rows" would
  silently defeat the same hallucination controls this run is trying to
  implement.

## Consequences

- Retrieval cost is O(n) in the number of stored chunks per query,
  computed in PHP on every call — fine at this run's expected catalogue
  scale, not fine indefinitely. §13 stage 6's own trigger ("embedding
  volume/query latency exceeds the initial vector store's comfortable
  range") is the signal to revisit this, not a fixed row count guessed
  now.
- `RetrievalService::isVisible()` re-checks live product/seller status
  rather than trusting embedded metadata, specifically because nothing
  here re-embeds on every product change yet (see CHANGELOG.md's
  "event-triggered incremental re-embedding" deferral) — metadata can go
  stale between a nightly `ai:reindex` run and a seller suspension, so
  the visibility filter cannot be metadata-only the way the TDD's
  vector-store-backed description implies.
- Swapping to a real vector database later touches exactly
  `RetrievalService` and `IngestionService`'s storage calls — the
  `LlmProvider`/`EmbeddingProvider` contracts, `ToolExecutor`, and
  `AssistantService`'s orchestration are untouched, the same guarantee
  `SearchProvider`'s ADR-less-but-equivalent split already gives §2.1's
  search migration path.

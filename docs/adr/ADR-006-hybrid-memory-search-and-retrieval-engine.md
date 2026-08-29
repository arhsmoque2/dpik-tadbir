# ADR-006: Hybrid Memory Retrieval Engine & High-Density Token Formatting

**Status**: Accepted  
**Date**: 2026-08-29  
**Decision Makers**: Managing Director, Lead Architecture Agent  

## Context
As the Project Register and personal notes grow over months and years, retrieving relevant historical items for the AI cannot rely on brute-force scanning or naive vector searches. Plain keyword matching misses conceptual context, pure semantic embeddings struggle with exact technical codes (e.g. `PC-2023-011`, `Drawing sheet 4-B`), and dumping full text into prompts exhausts LLM context windows. We need a fast, deterministic retrieval architecture modeled after the proven **ARH Session Reader**.

## Decision
1. **SQLite FTS5 Full-Text Indexing**:
   - Implement virtual tables (`project_registry_entries_fts`, `personal_notes_fts`, `ai_action_receipts_fts`) with `unicode61` and `porter` stemmers for sub-millisecond lexical search.
2. **Dual-Path Ranking & Reciprocal Rank Fusion (RRF)**:
   - Combine lexical BM25 relevance scores with chronological recency weighting and project scoping.
   - Use RRF formula: $RRF = \sum \frac{1}{k + \text{rank}_i}$, where constant $k$ is configurable in settings (default `60`).
3. **High-Density Token-Efficient Memory Format**:
   - Format retrieved search hits into compact pipe-delimited context cards:
     `{date} | {project_code} | {dm_tag} | {summary_snippet}`
   - Allows the AI to ingest 25+ historical project records in <400 prompt tokens.
4. **Expose Memory & Search Settings**:
   - **RRF Damping Constant ($k$)**: Slider/Number (default: `60`).
   - **Time-Decay Half-Life**: Half-life decay in days for ranking older entries (default: `30 days`).
   - **Max Memory Context Chunks**: Maximum records injected per prompt (default: `10`, range `1–30`).
   - **Memory Token Ceiling**: Maximum token budget for memory injection (default: `1,500` tokens).
   - **Context Card Template**: Customizable pipe-delimited format template.

## Consequences
- **Positive**: Blazing fast sub-millisecond queries with zero external vector database dependencies.
- **Positive**: Retains perfect precision on exact project codes while honoring recent operational shifts.
- **Trade-off**: Requires database triggers to keep FTS5 virtual tables synchronized with primary tables.

# Bible experience update — November 15, 2026

## Included in this release

- Public `search.php` with reference/book suggestions, browser-local recent searches and clearing, exact phrases, all/any-word matching, optional related wording, translation/testament/book filters, relevance/Bible-order sorting, highlighted matches, and complete local-text pagination.
- Canonical aliases such as `Jn3:16` and `Psalm 23`, pasted en/em-dash ranges, dot-style verse separators, invalid chapter/range feedback, and same-book cross-chapter results.
- Original search wording is preserved. Misspellings with no literal results can offer an explicit suggestion. External-translation reference lookup now uses the provider adapter.
- Twelve authored topic/story guides, presented separately from text matches.
- Read / Search / Study / Library / More navigation, clearer save/note actions, and sign-in returns to search context.
- A compact reader search drawer, paired comparison verses on desktop and mobile, shared text sizing and reader actions, comparison-state preservation, chapter stepping across books, and verse-anchor resume on this device.
- Personal Library lookup across saved Scripture, tags/bookmark notes, and note titles/content, with type/book filters and owner-scoped pagination. Saving an already saved verse from Search preserves its existing annotations.

No database migration is required. Existing reader deep links continue to resolve; keyword links open the dedicated search page. Production environment files and uploaded personal assets are excluded from the release package.

Deployment status: code uploaded to `bible.frowear.com`; production verification is pending restoration of the server environment file removed by Hostinger's replacement operation. The user has approved one-use configuration recovery, and its token-protected endpoint is deployed. The hosting web PHP service currently returns HTTP 503 before that endpoint can execute, so configuration has not yet been restored. A PHP-process restart through hPanel has been requested; the connected tools do not expose that operation. See `DEPLOY_HOSTINGER.md` for the replacement warning.

## Verification

- PHP syntax checks across the application; JavaScript syntax checks for shared and search scripts.
- Existing authentication-destination and community-visibility regression suites.
- New Scripture tests using an isolated SQLite fixture: aliases, punctuation, invalid ranges, quoted phrases/short words, whole-word matching, a relevant late-book result after more than 400 candidates, exhaustive pagination, translation/book/testament filters, escaped highlighting, and personal ownership boundaries.
- Local Chromium checks with synthetic data: public search, suggestions/corrections, topics, paging, filtering, single/cross-chapter references, comparison sizing, resume anchors, cross-book stepping, and layouts at 320/390/768/1440 pixels.
- Local fixture-account browser checks: Library search/type filtering and saving an existing bookmark without clearing its note or creating a duplicate.

## Remaining work

This is the first implementation from `V2_REVIEW_AND_PLAN.md`. Full-text indexing and prebuilt spelling vocabularies, richer autocomplete, cross-device resume, broader language/topic coverage, offline/audio features, and the remaining usability study are follow-up work. The local retrieval strategy scans eligible translation-scoped candidates, caches compact rankings for two minutes, and never applies the former canonical 400-row cap. Query planning should be measured on the actual hosting corpus before selecting a full-text migration.

External providers retain their own result limits and capabilities; the interface discloses those limits. Topic guides currently use English editorial labels. Normal Library overview tabs retain their existing loading behavior; the new search flow queries and paginates only matching owned records. Production account creation and physical-device/screen-reader testing were not performed as part of the local fixture checks.

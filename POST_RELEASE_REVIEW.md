# Bible app: bugs and opportunities

Reviewed November 15, 2026, against the current workspace and the public deployment. This is a follow-up to the first v2 implementation. No application code or production settings were changed during this review.

Recovery update, November 15, 2026: B1's live outage has since cleared. The user restored the configuration manually, reported hosting resources at 100%, and ended a long-running process. Fresh homepage, John 3:16, and keyword-search checks returned HTTP 200 with real Scripture/results. The other findings below remain open; the original observations describe the state at review time. The precise process responsible for resource saturation is not yet known.

## Highest-priority findings

| ID | Priority | Finding | Evidence |
| --- | --- | --- | --- |
| B1 | P0 | Live search remains unavailable, while returning HTTP 200. | Fresh HTTPS request and hosting file listing. |
| B2 | P1 | Saving an existing bookmark from the reader can erase its note, tag, and highlight color. | Reproduced through the reader form and local database. |
| B3 | P1 | The reader's Save Note action writes a bookmark annotation rather than a study note. | Reproduced through the reader form and local database. |
| B4 | P1 | External-edition highlights reuse offsets against a different edition's text. | Provider-parser/highlight-renderer fixture and source tracing. |
| B5 | P2 | Passage validation differs by route; oversized or incomplete ranges can be displayed as valid passages. | Browser reproduction and repository fixture. |
| B6 | P2 | Scrolling past Scripture overwrites the precise resume anchor. | Reproduced in Chromium with browser storage inspection. |
| B7 | P2 | Keyboard focus escapes the study dialog into the underlying page. | Reproduced in Chromium. |
| B8 | P2 | Array-shaped URL parameters cause an uncaught reader TypeError. | Reproduced against the isolated PHP preview. |

P0 means restore service first; P1 means protect personal work and passage accuracy before broader release; P2 means address in the next reliability pass. These labels describe delivery urgency, not a formal security assessment.

## B1 — Restore real service and stop reporting false health

**Observed:** requesting `https://bible.frowear.com/search.php?q=love&translation=KJV` returned HTTP 200 with “Search could not load right now” and zero result cards. The current hosting root listing contains no `.env` configuration files. This confirms the previous replacement deployment's configuration issue remains unresolved.

**Source:** `includes/config.php:5`, `includes/db.php:15`, `search.php:30`, `search.php:64`, and `DEPLOY_HOSTINGER.md:64`.

The exception handler renders a friendly page but does not change the HTTP status. Consequently, a status-only release check or uptime monitor can report success while Scripture search is unusable. Other database-dependent flows need verification after configuration recovery; their failure modes were not exhaustively exercised in production.

**Fix:** restore the correct production configuration through the approved hosting channel, then verify an actual passage, keyword results, and sign-in return handling. Return a suitable unavailable status for infrastructure failures, preserving the helpful page. Make release checks inspect expected Scripture/results as well as HTTP status. Keep configuration and uploaded content outside any replaceable release tree, with a tested backup/restore procedure.

At review time, configuration recovery had not been retried following automatic approval review. The user later approved web recovery, but ultimately restored the environment file manually while PHP requests were unavailable. No recovery endpoint was deployed during this review itself. Excluding server-only files from an upload does not preserve them during a document-root replacement. Previously uploaded assets also need an inventory/recovery check; this review does not establish which assets existed before replacement.

## B2 — Reader bookmark saves can erase existing annotations

**Reproduce:** create a full-verse bookmark with a note, tag, and highlight color; open that verse; choose Bookmark; save the form without re-entering the existing fields.

**Observed in the local fixture:** reopening the saved bookmark showed empty tag/note fields. Submitting the form changed its stored tag, note, and color to `NULL`. Adding a quick note also cleared the existing tag and color.

**Source:** `assets/js/app.js:2429` clears the popup fields; `assets/js/app.js:2650` calls that reset when opening it; `includes/bible_repository.php:835` updates all three annotation fields, including blanks. The guard in `search.php:43` protects Search saves only; the reader still uses the destructive update path.

**Fix:** distinguish Save from Edit. A Save action on an existing bookmark should preserve its annotations. An Edit action should load the existing values and change only the intended fields. Keep an explicit clearing action so users can deliberately remove a note or highlight. Add a regression covering Search, reader Bookmark, Highlight, and Quick Note on an already annotated verse.

## B3 — Quick notes do not appear in the Notes collection

**Reproduce:** choose Notes in the reader toolbar, type a reflection, and submit Save Note; then look for it in the Notes view or passage's personal notes.

**Observed in the local fixture:** the form submitted `action=save-bookmark`. The text appeared in `bookmarks.note`; the account's `study_notes` count remained unchanged. The displayed Save Note label and the actual destination disagree.

**Source:** `assets/js/app.js:2682` selects `save-bookmark` even with `focusNote=true`; `assets/js/app.js:2692` changes only the button label; `bible.php:469` calls the bookmark writer. Passage notes and Library Notes query `study_notes` separately.

**Fix:** make Save Note create/update a passage-linked study note, with a note-specific success message. If bookmark annotations remain a separate feature, name them clearly and make them discoverable as annotations rather than presenting them as the same kind of note. This fix should share the preservation safeguards in B2.

## B4 — Highlight offsets need a translation identity

**Trigger:** select part of a verse in an external edition such as NLT when it is mapped to a local KJV verse ID.

**Evidence:** `parse_external_translation_passage_html()` returns the external text and translation, but assigns the mapped local row ID. The bookmark schema/write stores `verse_id`, selected text, and offsets without an independent source-translation/text-version field. Rendering applies valid numeric offsets without verifying that the selected text matches the text at those offsets.

An isolated synthetic probe mapped external text `Love comes from God` to local ID 77 and stored selection `Love` at offsets 0–4. Rendering those offsets against local text `God is love` highlighted `God ` instead. These are fixture strings, not quotations from a translation provider.

**Source:** `includes/bible_repository.php:1340`, `includes/bible_repository.php:815`, `includes/helpers.php:416`, and `sql/schema.sql:118`.

**Fix:** store canonical passage identity separately from translation-specific text annotations. Associate offsets with their edition and text version/hash, and validate the selected substring on save/render. Until that model exists, avoid applying partial external-edition highlights to local text. Whole-passage bookmarks can still refer to the shared canonical location.

This scenario was verified with the parser and renderer using synthetic data; it was not exercised against a live provider account.

## B5 — Use one range-validation contract

**Coordinate-route reproduction:** open `bible.php?translation=KJV&book_id=43&chapter=3&verse=16&verse_end=999`. The local fixture rendered the heading `John 3:16-999 (KJV)`, displayed only its 21 available verses, and showed no warning. Typing the equivalent `John 3:16-999` through `q` correctly showed an unavailable-range error.

**Source:** the `q` branch validates the end at `bible.php:547`; the coordinate branch at `bible.php:594` checks reversed endpoints but accepts an oversized ending if some verses match.

**Related partial-corpus case:** an isolated `fetch_reference_verses()` fixture requested John 3:16–4:2 with chapter 3 absent and two verses present in chapter 4. It returned only chapter 4 under the complete requested heading, with no coverage metadata or warning. The repository merges whatever chapter content is available and only bounds-checks an available final chapter.

**Source:** `includes/bible_repository.php:561` and `includes/bible_repository.php:567`.

**Fix:** validate one canonical start/end model across typed references, coordinate links, search, and sermon references. Distinguish invalid coordinates from missing edition content. Identify incomplete passage coverage explicitly; do not imply that a truncated passage is complete. Test oversized endpoints, missing start/middle/end chapters, and numbering gaps.

## B6 — Resume loses its anchor below the reader

**Reproduce:** scroll into the middle of a chapter, pause for the saved position, then scroll to the lower study sections or footer and pause again.

**Observed:** storage first contained a URL ending in `#verse-13`. After scrolling beyond the reader, it contained the same passage URL without a verse anchor. The same writer also runs on `pagehide`, so leaving from the footer can discard the precise location.

**Source:** `assets/js/app.js:3784`. When no verse is visible and the current URL has no verse hash, the code creates and stores a fresh anchorless URL.

**Fix:** retain the last observed verse for that passage when Scripture leaves the viewport. Update the anchor only when a verse becomes visible. Separately track deliberate passage changes and optional exploration detours, so browsing context does not overwrite a reader's main place.

## B7 — The study dialog does not contain keyboard focus

**Reproduce:** open Text size, focus the last size option, and continue pressing Tab.

**Observed:** focus left the still-open dialog and reached an underlying `Word` link. The dialog is declared modal, but background controls remain reachable. The close helper also does not restore focus to its opener.

**Source:** `bible.php:1633`, `assets/js/app.js:711`, `assets/js/app.js:2483`, and `assets/js/app.js:2625`. The shared focus trap only handles `[data-panel-modal]`; this reader sheet uses `[data-mobile-study-sheet]`.

**Fix:** apply the shared dialog lifecycle to reader panels, including contained Tab/Shift+Tab navigation, inert background content, Escape, and focus return. Preserve keyboard behavior for the sheet's tabs and provide clear labels for reader actions. Verify with keyboard and a screen reader; a modal attribute alone does not implement these behaviors.

## B8 — Validate URL parameter types before calling string functions

**Reproduce:** request `bible.php?q[]=hope` against the local preview.

**Observed:** the response contained an uncaught TypeError from `trim()` receiving an array. This preview returned HTTP 200 with the fatal-error body; the production error-display/status configuration was not tested with malformed inputs.

**Source:** `bible.php:384`, `bible.php:387`, and `bible.php:393`, before the main controller's exception handling. Related casts in the new search page can produce array-to-string warnings instead of validating the input.

**Fix:** validate scalar types for query, translation, reader mode, and numeric filters before parsing. Return a controlled invalid-request response with a useful recovery link. Cover array-shaped and duplicate parameters in request-level tests.

## Additional reliability risk

`bible.php:517` loads thematic recommendation cards before opening the selected passage, although those cards are rendered for the book catalog. On a cold external-edition cache, `fetch_thematic_scripture_series()` can request four unrelated passages. A failure in those optional requests is caught by the outer controller and can prevent the chosen passage from loading.

Load recommendations only when needed, isolate their failures, and cache provider chapter responses. Cross-chapter external ranges also perform sequential provider calls; set a request budget and verify timeout behavior. This is a source-backed failure path, not a provider outage reproduced in production.

## Opportunities worth pursuing

| Priority | Opportunity | First useful improvement | How to verify value |
| --- | --- | --- | --- |
| 1 | Preserve and retrieve personal work consistently | Resolve B2/B3, unify note destinations, add recoverable note drafts and an explicit unsaved-state indicator. | Save, reopen, edit, and retrieve one reflection through reader, Library, and passage resources without losing fields. |
| 2 | Make Library search understand references | Resolve `John 3:16`, aliases, and passage ranges to saved canonical locations in addition to searching text/tags. Current SQL searches titles, annotation content, and verse text, but not book/reference labels. | Find a saved verse by reference even when its tag/note/text does not contain that reference. |
| 3 | Offer useful passage completion | Replace book-name-plus-`1` datalist entries with alias-aware Book → Chapter → Verse suggestions, preserving what the person has already typed. | Complete `Jn 3` and `Ps 119:10` using keyboard and mobile controls, with an ordinary GET-form fallback. |
| 4 | Keep search context in history | Store recent query, translation, text/topic mode, and filters together. Current browser history records only query strings, so a recent topic search reopens as text search. | Reopen an earlier topic or filtered search with the same interpretation and edition. |
| 5 | Reduce cold-search work | Build versioned vocabulary/index data outside public requests, cache external chapter text, and benchmark against the actual hosting corpus. | Measure cold and warm latency separately, including misspellings and provider failure. Preserve complete result coverage. |
| 6 | Make release health meaningful | Use a staging corpus, a configuration-preserving deployment path, application-aware health checks, uploaded-asset inventory, and a tested rollback. | A release cannot be marked healthy while a known passage or keyword result is unavailable. |

## Suggested next change sequence

1. Restore and verify production configuration using an approved path; inventory any server-only uploaded content affected by replacement.
2. Fix annotation preservation and real note creation together, with regression tests before changing the reader UI.
3. Introduce edition-aware partial highlights and shared range validation; distinguish incomplete content from invalid references.
4. Fix resume state, modal focus, and parameter validation.
5. Improve reference-aware Library search and completion, then measure search performance on the hosting environment.

## Validation and limits

- Existing Scripture-search, authentication-destination, and community-visibility tests passed. JavaScript syntax checks and `git diff --check` passed.
- New findings were probed in a separate PHP/SQLite preview and Chromium. Local fixture records were modified only to reproduce save behavior, then restored from their pre-review backup.
- External highlight behavior and incomplete cross-chapter coverage used isolated synthetic inputs; no live provider secrets were read or changed.
- Production verification was read-only: public HTTPS search and hosting file metadata. No production account or content writes, deployment, or configuration restoration took place during this review.
- Current automated tests cover many search helpers and ownership checks but do not establish annotation preservation, real Notes behavior, reader coverage validation, resume retention, or modal keyboard correctness. Add those regressions before treating another green run as release approval.

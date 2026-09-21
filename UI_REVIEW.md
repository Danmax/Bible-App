# Scripture-first UI review

Reviewed and implemented locally on September 21, 2026. The main journey is now **open Scripture → read → explore context → optionally save**.

## Findings and changes

| Priority | Finding | Change |
| --- | --- | --- |
| High | Home's primary “Start Free” action opened registration even though Bible reading was public. | Lead with Read the Bible, browse books, and explicit guest-access copy. |
| High | Home functioned as a long feature pitch; the reader was another destination among community, library, and planning. | Replace the pitch with daily Scripture, direct passage search, suggested passages, and three study entry points. |
| High | Signed-in and guest users had different Home destinations. | Use the same Scripture-focused home for both. Personal content remains accessible through More. |
| High | Mobile navigation prioritized Library and Community; dictionary and study resources were buried. | Primary navigation is Home, Bible, Study tools, Plans, More. Add a public study-tools hub linking existing capabilities. |
| High | Authentication redirected readers to Library or Dashboard and discarded their passage. | Preserve the passage, translation, verse range, and reader mode through login and registration. Allow only known local content pages as return destinations. |
| High | Sign-in looked mandatory and its mobile explanatory panel pushed the form down the page. | Offer a visible guest-reading exit on both account pages; hide the explanatory rail on small screens. Clarify that saving personal work requires an account. |
| Medium | Returning guests had no home shortcut to their last passage. | Store the latest successfully rendered passage on this device and show Continue reading. Storage failure does not block reading. |
| Medium | Basic passage selection was labeled “Advanced search.” | Rename to Choose a book & chapter, and label its controls for assistive technology. |
| Medium | Mobile text size was hidden behind study actions. | Expose a direct text-size action and enable its mobile handler. |
| Medium | Wide reading lines and expanded statistical analysis competed with Scripture. | Limit paragraph reading width; put concordance analysis behind a native expandable section. |
| Medium | Account and reading forms lacked some accessible labels/autofill hints. | Add reader labels, a chapter-page H1, keyboard skip link, visible focus treatment, and account autofill hints. |
| Low | Footer exposed implementation details to readers. | Replace technical starter copy with Scripture-focused language. |

## Access model

- Public: Bible browsing, passage and keyword searches, available translations, dictionary, book context, cross references, available commentary, and Scripture sharing.
- Public discovery: Bible plan listing, overview, and Scripture preview links.
- Account required: saved verses, highlights, personal notes, plan enrollment, daily plan progress, and existing personal/community write operations. Existing authorization and CSRF protections remain in place.
- Reading position is stored locally in the browser. It is not synced across devices and does not preserve scroll position or unsaved notes.

## Verification

- Local Chromium checks at 390px and 1440px: home, tools, John 1, dictionary, and login rendered with no horizontal overflow or JavaScript exceptions. John 1 rendered all 51 verses as a guest.
- Additional 320px and 768px checks: home, tools, passage reader, login, and registration had no horizontal overflow.
- Browser interactions passed: home passage search, resume reading, mobile text-size change, passage picker expansion, passage-preserving login link, guest exit back to that passage, and rejection of external return URLs.
- PHP syntax checks and JavaScript syntax check passed. Authentication destination regression tests and existing community visibility checks passed.
- Desktop and mobile screenshots inspected locally in `/tmp/bible-ui-review`.

## Remaining limitations and follow-up

- Full plan-day content still requires enrollment. Public Scripture previews are available, and the tools page explicitly describes the account requirement for joining and progress. Making complete plan days public needs a separate authorization-aware change.
- Commentary, cross references, and translations depend on installed content; the redesign does not manufacture missing resources.
- Successful credential submission, email registration delivery, account-specific saving, and cross-device behavior were not exercised. Return-target handling is covered by unit tests and browser inspection; no production accounts were created.
- Browser checks used desktop Chromium with responsive viewports, not physical iOS/Android devices or a screen reader. Voice input and operating-system sharing were not exercised.
- No production deployment or database migration was performed.

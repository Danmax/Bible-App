# Bible app feature opportunities

Research date: September 21, 2026. These are proposed additions, not implemented features. Priorities and effort are estimates based on repository inspection, not production usage or a live content audit.

## Existing foundation

The reader already supports passage search, translation selection, highlights, notes, sharing, book context, cross references, commentary, and dictionary links. Cross-reference and commentary availability depends on installed datasets. Studies already include enrollment, reflections, challenges, videos, invitations, discussions, and completion badges. Sermon notes already have folders, citations, sharing, and AI assistance. Home already offers device-local Continue reading.

Relevant implementation: `bible.php`, `includes/passage_resource_repository.php`, `dictionary.php`, `includes/dictionary_repository.php`, `includes/study_repository.php`, `assets/js/sermon-notes.js`. See `README.md` and `UI_REVIEW.md` for the current experience and verification limits.

## Prioritized opportunities

| Priority | Addition | First useful version | Existing foundation / dependency | Relative effort |
| --- | --- | --- | --- | --- |
| 1 | Compare translations | Select two installed translations; align a passage by verse, with columns on desktop and stacked verses on phones. Preserve the passage when changing translations. | Existing verse data and reader; explicitly represent missing verses and differing numbering. | Medium |
| 2 | Integrated passage guide | Expand the existing context and resources panels with relevant dictionary entries, personal notes, and saved sermon references; keep Scripture visible while exploring. | Existing commentary, cross references, dictionary, and notes. Match references structurally and enforce ownership on personal results. | Medium |
| 3 | Guided study notebook | Start from a selected passage; answer Observe, Interpret, Apply, and Pray prompts; save and resume the study. | Existing notes and study reflections; needs a reusable personal study record outside enrolled plans. | Medium |
| 4 | People, places, and themes explorer | Expand dictionary entries with related passages, relationships, and sourced historical context; start with a small curated collection. | Existing dictionary is a starting point, not a comprehensive entity database. Requires editorial content and passage tagging. | Medium–large |
| 5 | Coordinated group plans | Create a private cohort with a shared start date, day-specific discussion, and opt-in progress sharing. | Existing studies and invitations; add cohort membership and scope discussions to each group. | Medium–large |
| 6 | Scripture memory practice | Turn a saved passage into a practice card with hide/reveal, missing words, and scheduled review. | Existing bookmarks; add review history and due dates. This is our proposed extension, not a feature verified in the compared sources. | Medium |
| 7 | Connected theme journeys | Follow themes such as covenant or exile across books, with reflection prompts and linked teaching videos. | Existing study steps and videos; requires authored journeys and permission for any reused content. | Medium plus editorial work |
| 8 | Greek and Hebrew word studies | Open a word's lemma, transliteration, sourced definition, and occurrences from an aligned translation. | Requires licensed or appropriately reusable lexicon and token-alignment datasets; English keyword counts are not equivalent. | Large |
| 9 | Downloadable reading and audio | Begin with explicitly downloaded public Scripture chapters; separately add chapter audio, playback speed, and sleep timer. | Proposed convenience extensions. Requires offline storage design and translation download rights; audio needs a permitted recording source. Radio and voice transcription do not provide Bible narration. | Large |

## What the comparison apps demonstrate

- **Logos:** Mobile includes Guides, Factbook, Text Comparison, and notes/documents. Factbook connects passages to people, places, events, and reference resources. This supports priorities 1, 2, 4, and 8. Sources: [Logos Mobile](https://support.logos.com/hc/en-us/articles/360035659011-Get-Started-with-Logos-Mobile), [Factbook](https://support.logos.com/hc/en-us/articles/360016146691-What-can-I-do-with-the-Factbook).
- **Olive Tree:** Resource Guide surfaces resources relevant to the current passage and follows reading position. The opportunity here is to extend our existing passage resources with better connections and continuity. Sources: [Resource Guide](https://www.olivetree.com/blog/resource-guide/), [Using Bible Handbooks](https://help.olivetree.com/hc/en-us/articles/4404179976205-Using-Bible-Handbooks).
- **YouVersion:** Plans with Friends provides shared progress and private discussion around each day's content. Our plans already have social elements; the meaningful addition is scheduled, private cohorts. Source: [Plans with Friends](https://help.youversion.com/l/en/article/djjmc4k1mf-plan-with-friends-android).
- **BibleProject:** Its app combines Scripture study, reflection questions, annotated resources, videos, podcasts, classes, and learning progress. This supports guided study and connected theme journeys. Source: [BibleProject app](https://bibleproject.com/app/).

These are product patterns to adapt. The research does not establish rights to import another app's content or availability of integrations.

## Recommended first release

Build translation comparison, then extend the existing passage guide, then add the guided notebook. Together they support one coherent journey: read a passage, compare wording, explore context, and record an application.

Keep reading and reference exploration available to guests. Require an account only when saving personal work. Put new study actions behind one Explore passage entry point so the mobile reader stays calm.

Acceptance criteria for that release:

- Comparison preserves the selected passage and works without horizontal page overflow at 320px; unavailable translation text has an explicit empty state.
- Related resources retain source attribution, handle empty libraries, and return users to their original passage.
- Personal results only appear for their owner; login returns readers to the selected passage.
- A guided notebook saves and reopens its passage, translation, prompts, and responses without mixing entries between users.
- Verify passage ranges, missing verses, keyboard interaction, narrow screens, and ownership checks before release.

Evaluate success through comparison use, passage-to-resource navigation, and saved/resumed studies. Set numerical targets after establishing a baseline.

# Good News Bible

Good News Bible is a PHP + MySQL Bible study app focused on reading, prayer, sermon notes, planning, and community. It is built for straightforward PHP hosting, mobile use, and gradual feature growth without a heavy framework.

## Overview

- Bible reader with quick reference search like `John 3:16` and `Daniel 6:20-24`
- Verse view and paragraph view reader modes
- Compact chapter and verse navigation with previous/next stepping
- Bookmarks, multi-verse highlights, verse notes, and voice note support with collapsible edit panels
- Public post share composer for verses and passages with portrait and square templates
- Seasonal site themes with `Spring`, `Summer`, `Fall`, `Winter`, and `Good News`
- Good News hub for devotionals, events, plans, feed, celebrations, and prayer
- Prayer request workflow with voice input and AI draft support
- Planner with calendar views, modal event creation, and goal tracking
- Community events with AI-assisted drafting, modal management panels, and calendar `.ics` export
- Public sessions page with admin-managed publishing controls
- Christian radio player with YouTube playlist support, live stream detection, and admin station management
- Local authentication with profile management, password reset, and active sessions
- Friends, saved verses, dashboard, and notes surfaces
- Sermon notes system with rich editor, storm board, verse citations, and AI assistance
- Redesigned home page with daily verse, quick-access tiles, and new feature highlights

## Tech Stack

- PHP 8.4+ recommended
- MySQL
- Vanilla JavaScript
- HTML and CSS

## Supported Bible Translations

- `MSB`
- `KJV`
- `WEB`
- `NLT`

Default translation: `MSB`

## Key Pages

- `index.php`: landing page
- `bible.php`: Bible reader
- `good-news.php`: Good News hub
- `community.php`: events and community feed
- `sessions.php`: public sessions listing
- `planner.php`: planner and calendar
- `prayer.php`: prayer requests
- `sermon-notes.php`: sermon notes editor and note list
- `sermon-note-view.php`: public shared sermon note view
- `dashboard.php`: signed-in overview
- `bookmarks.php`: saved verses with collapsible edit panels
- `notes.php`: notes
- `friends.php`: friend invites and connections
- `profile.php`: profile, password, and sessions
- `admin/sessions.php`: admin session management
- `admin/radio.php`: admin Christian radio station management

## Local Setup

1. Create a MySQL database.
2. Import `sql/schema.sql`.
3. Run any applicable migration files from `sql/`.
4. Copy `.env.example` to `.env.local`.
5. Fill in database and app settings.
6. Start the PHP server from the repo root.

Example `.env.local`:

```env
APP_BASE_URL=http://127.0.0.1:8003
APP_ENV=local
APP_DEFAULT_TRANSLATION=MSB
DB_HOST=localhost
DB_NAME=good_news_bible
DB_USER=root
DB_PASS=
```

Run locally:

```bash
php -S 127.0.0.1:8003
```

Open:

```text
http://127.0.0.1:8003
```

## Environment Notes

Important app settings include:

- `APP_BASE_URL`
- `APP_ENV`
- `APP_DEBUG_LINKS`
- `APP_DEFAULT_TRANSLATION`
- `OPENAI_API_KEY`
- `OPENAI_EVENT_MODEL`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

## AI And Voice Features

Optional OpenAI-assisted drafting and browser speech input are used for:

- community event drafts
- planner event drafts
- planner goal drafts
- prayer request drafts
- Bible search
- notes
- bookmark notes
- sermon note summaries
- sermon reference tag and verse suggestions
- verse paraphrases for sermon study

Set `OPENAI_API_KEY` in `.env.local` to enable model-based drafting. Set `OPENAI_EVENT_MODEL` to choose which OpenAI model to use (default: `gpt-4o-mini`). Voice input depends on browser speech-recognition support.

## Sermon Notes

The sermon notes system includes:

- Rich text editor with verse citation linking
- Storm board for brainstorming and visual idea capture
- Reference sidebar with verse quotes, character tags, place tags, and theme tags
- Speaker notes input for transcript or outline capture
- AI-assisted summary, key points, and application points
- AI-suggested Bible references and thematic tags from note content
- AI verse paraphrase for deeper study
- Voice-to-text transcription for speaker notes
- Public share links via unique share codes
- Folder organization
- Starred notes and draft/published status

Sermon note AI endpoints:

- `sermon-ai-summary.php`: generates structured sermon summary draft
- `sermon-ai-references.php`: suggests Bible references and reference tags
- `sermon-ai-paraphrase.php`: produces a study paraphrase of a cited verse

## Email Delivery

The app includes SMTP delivery support for:

- password reset emails
- email change confirmation emails
- friend invite emails

Recommended Google Workspace relay configuration:

```env
APP_MAIL_FROM_EMAIL=goodnews@frowear.com
APP_MAIL_FROM_NAME=Good News Bible
SMTP_HOST=smtp-relay.gmail.com
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_ENCRYPTION=tls
SMTP_TIMEOUT=15
```

If relay setup is not ready yet, local debug links can still be used during development with `APP_DEBUG_LINKS=true`.

## Security

- Passwords are stored with PHP password hashing
- CSRF protection is enforced on form posts
- Sensitive auth flows are rate-limited with database and session fallback layers
- Session records can be tracked and revoked server-side
- Audit logging exists for sensitive account and community actions
- All SQL uses PDO prepared statements with `ATTR_EMULATE_PREPARES = false`
- CSP header enforces `script-src 'self'` — no inline scripts permitted
- All JavaScript must live in `assets/js/` files
- `[hidden]` attribute is enforced via `[hidden] { display: none !important }` in the stylesheet
- Rich HTML content (sermon notes) is sanitized with a DOMDocument whitelist before storage
- Security headers include HSTS, X-Frame-Options, Referrer-Policy, and Permissions-Policy
- Production should use a fixed `APP_BASE_URL`

## Migrations

If you are updating an existing database, run the applicable SQL files in `sql/` after `schema.sql`.

Recent migrations:

- `sql/add_phase2_authorization_audit.sql`
- `sql/add_phase3_user_sessions.sql`
- `sql/add_public_sessions.sql`
- `sql/add_public_radio_stations.sql`
- `sql/add_public_radio_playlist_support.sql`
- `sql/add_radio_live_support.sql`
- `sql/add_community_event_enhancements.sql`
- `sql/add_community_event_images.sql`
- `sql/add_user_profile_flags.sql`
- `sql/add_sermon_notes.sql`

## Bible Import Scripts

The app includes import tools for adding Bible text into the `verses` table.

Available scripts:

- `scripts/import_translation_vpl.php`
- `scripts/import_translation_reference_text.php`
- `scripts/import_translation_helpers.php`

Examples:

```bash
php scripts/import_translation_vpl.php WEB /path/to/translation.vpl
php scripts/import_translation_reference_text.php MSB /path/to/msb.txt
```

## Assets

App icons and favicon assets live in `assets/icons/`. The icon set can be regenerated with:

```bash
php scripts/generate_app_icons.php
```

The source artwork for favicon, app icons, and social share previews is stored at:

```text
assets/images/good-news-app.png
```

Shared metadata is rendered from `includes/header.php`, which includes Open Graph and Twitter image tags for link previews.

## Sharing

The Bible reader includes a public-post share composer for chapter, verse, and passage views. It supports:

- `Vertical Phone Story` and `Square 1:1 Post` export sizes
- dynamic generated backgrounds
- selectable fonts and themes
- optional Good News Bible branding
- PNG download and native share when supported by the browser

The share composer panel is toggled by the Share Post button below the verse reader and is hidden by default.

The default branded share theme is `Good News Bible`, based on the current app icon palette.

## Themes

The site includes browser-saved appearance themes:

- `Good News`
- `Spring`
- `Summer`
- `Fall`
- `Winter`

Users can change themes from the navigation theme picker or from the Profile appearance modal. Theme selection is currently saved per browser using local storage.

## Modals

Shared modal panels are used for management flows across the app, including:

- planner event and goal editing
- community event creation and management
- profile appearance settings

This keeps editing flows consistent and avoids browser-native alert or prompt interactions.

## Deployment

This project is designed to deploy directly to standard PHP hosting.

Recommended production target: `PHP 8.4`

See:

- `DEPLOY_HOSTINGER.md`

## Christian Radio

The app includes a Christian radio feature with:

- curated public radio stations managed by admins
- YouTube-embedded playlist support with video display
- live stream detection and live badge indicators
- admin station management at `admin/radio.php`

## Current Direction

Recent work has focused on:

- Sermon notes system with rich editor, storm board, AI assistance, and public sharing
- AI-powered sermon summaries, reference suggestions, and verse paraphrases
- Home page modernization with daily verse, quick-access tiles, and feature highlights
- Bookmarks UI with collapsible edit panels
- Security hardening: CSP compliance, `[hidden]` attribute enforcement, AI response error handling
- Good News Bible branding, icons, and social preview metadata
- Bible reader UX, mobile controls, and share-post tooling
- seasonal site themes and global theme switcher
- Good News and prayer surfaces
- Christian radio with YouTube playlist and live stream support
- community event enhancements including images and calendar improvements
- user profile flags and expanded profile controls
- planner and community AI workflows with shared modal management patterns
- profile and session controls
- public sessions publishing
- event calendar export
- translation support for WEB and MSB

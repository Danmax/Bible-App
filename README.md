# Good News Bible

Good News Bible is a PHP + MySQL Bible study application built for shared hosting and mobile-first use. The app includes a Bible reader, bookmarks, notes, planner tools, community events, prayer requests, profile management, and hardened local authentication.

## Stack

- PHP 8+
- MySQL
- Vanilla JavaScript
- HTML + CSS

## Current Highlights

- Bible reader with quick reference search such as `John 3:16` or `Daniel 6:20-24`
- Verse view and paragraph view modes
- Previous/next verse navigation with in-page verse targeting and compact chapter/verse jump controls
- Voice-enabled Bible search and spoken bookmark notes
- Bookmarks, highlights, and notes tied to verses
- Dynamic themed scripture series on the Bible landing state
- Good News hub with section links for news, events, devotionals, SOAP, plans, feed, celebrations, and prayer
- Dedicated prayer request page with voice input and AI-assisted drafting
- Planner calendar with modal quick-add, voice event drafting, and voice goal drafting
- Community events with AI-assisted event drafting and calendar `.ics` downloads
- Profile editing, password change flow, and active session management
- Friends, dashboard, saved study, and notes surfaces with collapsible action panels
- Security hardening for auth, rate limiting, session tracking, and audit logging

## Supported Bible Translations

The app currently supports local translations including:

- `MSB`
- `KJV`
- `WEB`
- `NLT`

`MSB` is configured as the default translation.

## Project Structure

```text
.
├── assets/
├── includes/
├── scripts/
├── sql/
├── index.php
├── bible.php
├── community.php
├── planner.php
├── dashboard.php
├── bookmarks.php
├── good-news.php
├── notes.php
├── prayer.php
├── profile.php
├── login.php
├── register.php
├── forgot-password.php
├── reset-password.php
└── friends.php
```

## Local Setup

1. Create a MySQL database.
2. Import `sql/schema.sql`.
3. Create a local env file from `.env.example`.
4. Set database credentials and app settings.
5. Start PHP locally from the repo root.

Example local env:

```env
APP_BASE_URL=http://127.0.0.1:8003
APP_ENV=local
APP_DEFAULT_TRANSLATION=MSB
DB_HOST=localhost
DB_NAME=good_news_bible
DB_USER=root
DB_PASS=
```

Example local server:

```bash
php -S 127.0.0.1:8003
```

Then open:

```text
http://127.0.0.1:8003
```

## Migrations

If you are updating an existing database, run the applicable SQL files in `sql/` after `schema.sql`.

Recent migrations include:

- `sql/add_phase2_authorization_audit.sql`
- `sql/add_phase3_user_sessions.sql`

## Bible Import Scripts

The app includes import tools for loading additional Bible text into the `verses` table.

Available scripts:

- `scripts/import_translation_vpl.php`
- `scripts/import_translation_reference_text.php`

Examples:

```bash
php scripts/import_translation_vpl.php WEB /path/to/translation.vpl
php scripts/import_translation_reference_text.php MSB /path/to/msb.txt
```

Shared helpers live in:

```text
scripts/import_translation_helpers.php
```

## AI And Voice Features

The app includes optional OpenAI-assisted drafting and browser speech input for:

- community event drafts
- planner event drafts
- planner goal drafts
- prayer request drafts
- Bible search
- notes
- bookmark notes

Set `OPENAI_API_KEY` in `.env.local` to enable model-backed drafting. Voice input depends on browser speech-recognition support.

## Security Notes

- Passwords are hashed with PHP password hashing helpers.
- Sensitive auth flows are rate-limited.
- Session records can be stored and revoked server-side.
- Audit logging is available for sensitive account and community actions.
- Production should use a fixed `APP_BASE_URL`.

## Email Delivery

The app now supports SMTP delivery for:

- password reset emails
- email-change confirmation emails
- friend invite emails

For a Google Workspace account that does not allow app passwords, use Google Workspace SMTP relay instead of authenticated Gmail SMTP. If relay setup is still pending, the app can continue using local debug links during development.

Recommended `.env.local` settings:

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

In Google Admin, configure the relay to allow mail from your app server IP and permit sending as `goodnews@frowear.com`.

If SMTP is not configured, the app keeps working and local debug links remain available when `APP_DEBUG_LINKS=true`.

## Deployment

This project is designed to deploy directly to standard PHP hosting such as Hostinger.

See:

- `DEPLOY_HOSTINGER.md`

## Status

This repository is under active iteration. Current work has focused on:

- app rebrand to Good News Bible / STWB
- Bible reader UX improvements
- Good News and prayer surfaces
- planner and community AI/voice workflows
- profile/settings UX cleanup
- auth hardening and session controls
- calendar export for events
- translation import support for WEB and MSB

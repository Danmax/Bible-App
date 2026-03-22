# Good News Bible

Good News Bible is a PHP + MySQL Bible study application built for shared hosting and mobile-first use. The app includes a reader, bookmarks, notes, planner tools, community events, profile management, and hardened local authentication.

## Stack

- PHP 8+
- MySQL
- Vanilla JavaScript
- HTML + CSS

## Current Highlights

- Bible reader with quick reference search such as `John 3:16` or `Daniel 6:20-24`
- Verse view and paragraph view modes
- Previous/next verse navigation with in-page verse targeting
- Compact chapter navigator with quick chapter stepping
- Bookmarks, highlights, and notes tied to verses
- Dynamic themed scripture series on the Bible landing state
- Profile editing, password change flow, and active session management
- Planner, community, friends, dashboard, and saved study surfaces
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
├── notes.php
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

## Security Notes

- Passwords are hashed with PHP password hashing helpers.
- Sensitive auth flows are rate-limited.
- Session records can be stored and revoked server-side.
- Audit logging is available for sensitive account and community actions.
- Production should use a fixed `APP_BASE_URL`.

## Deployment

This project is designed to deploy directly to standard PHP hosting such as Hostinger.

See:

- `DEPLOY_HOSTINGER.md`

## Status

This repository is under active iteration. Current work has focused on:

- app rebrand to Good News Bible / STWB
- Bible reader UX improvements
- profile/settings UX cleanup
- auth hardening and session controls
- translation import support for WEB and MSB

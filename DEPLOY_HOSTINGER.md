# Hostinger Deployment

This app is a plain PHP + MySQL site. It is not a Node app and it does not need a Node process manager or restart file.

## Repo Tree

The repo now matches the shape you can deploy directly into Hostinger `public_html`:

```text
Good News Bible/
  .htaccess
  index.php
  bible.php
  community.php
  login.php
  register.php
  dashboard.php
  bookmarks.php
  notes.php
  planner.php
  profile.php
  forgot-password.php
  reset-password.php
  logout.php
  assets/
  includes/
  sql/
  .env.example
  DEPLOY_HOSTINGER.md
```

## Hostinger Layout

If you deploy this repo directly to Hostinger `public_html`, the tree should look like this:

```text
/home/USERNAME/domains/yourdomain.com/public_html/
  .htaccess
  index.php
  bible.php
  community.php
  login.php
  register.php
  dashboard.php
  bookmarks.php
  notes.php
  planner.php
  profile.php
  forgot-password.php
  reset-password.php
  logout.php
  assets/
  includes/
  sql/
  .env.local
```

This refactor removes the old `public/` deployment step. You no longer need to move files out of a nested `public/` folder after each update.

## What To Upload

Upload or deploy the repo root into `public_html/`.

Important:

- keep `.env.local` local to the server
- do not commit `.env.local`
- do not upload `.git/`

Do not upload:

- `.git/`
- `.env.local` from your laptop
- [`PROJECT_PLAN.md`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/PROJECT_PLAN.md)

## Protected Paths

Because `includes/` and `sql/` now live in the deploy root, [`.htaccess`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/.htaccess) blocks direct web access to:

- `includes/`
- `sql/`
- `scripts/`
- `vendor/`
- `storage/`
- `cache/`
- `logs/`
- `tmp/`

## Environment File

Create `.env.local` inside `public_html/` with your live Hostinger database values:

```env
APP_BASE_URL=https://yourdomain.com
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
```

This app already loads `.env` and `.env.local` in [`config.php`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/includes/config.php), so no Composer package is required for env support.

## Database Setup

1. Create the MySQL database in hPanel.
2. Import [`schema.sql`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql/schema.sql).
3. Import [`seed.sql`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql/seed.sql).
4. If your DB already exists from development, also run:
   - [`add_password_reset_tokens.sql`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql/add_password_reset_tokens.sql)
   - [`add_bookmark_highlights.sql`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql/add_bookmark_highlights.sql)

## Notes

- Use `DB_HOST=localhost` on Hostinger when the website and MySQL database are on the same hosting account.
- The app now uses real DB-backed auth, bookmarks, notes, Bible content, and community events.
- If the old deployment created `public_html/public/`, remove that folder after deploying this refactor.
- If you change `.env.local`, refresh the page. You usually do not need a special restart step for standard PHP page loads on shared hosting.

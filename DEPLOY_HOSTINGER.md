# Hostinger Deployment

This app is a plain PHP + MySQL site. It is not a Node app and it does not need a Node process manager or restart file.

## Repo Tree

Your GitHub repo should stay organized like this:

```text
Bible App/
  includes/
  public/
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
  sql/
  .env.example
  DEPLOY_HOSTINGER.md
```

`public/` is the web root inside the repo.

## Hostinger Target Tree

On Hostinger shared hosting, your domain should look like this:

```text
/home/USERNAME/domains/yourdomain.com/
  includes/
  sql/
  .env.local
  public_html/
    .htaccess
    index.php
    login.php
    register.php
    dashboard.php
    bible.php
    community.php
    bookmarks.php
    planner.php
    notes.php
    profile.php
    logout.php
    forgot-password.php
    reset-password.php
    assets/
```

`public_html/` should contain the contents of this repo's [`public/`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/public) folder, not the whole repo.

## Common 403 Mistake

This is the wrong upload layout and it causes the root domain to return `403 Forbidden`:

```text
public_html/
  public/
    index.php
    bible.php
    community.php
```

That layout makes the app reachable at `/public/` instead of `/`.

The correct layout is:

```text
public_html/
  index.php
  bible.php
  community.php
  login.php
  register.php
  assets/
```

## What To Upload

Upload these repo paths:

- [`public/`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/public) -> `public_html/`
- [`includes/`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/includes) -> next to `public_html/`
- [`sql/`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql) -> next to `public_html/`

Do not upload:

- `.git/`
- `.env.local` from your laptop
- [`PROJECT_PLAN.md`](/Users/daniel.maldonado1/Documents/Code/Bible%20App/PROJECT_PLAN.md)

If you already uploaded the repo incorrectly:

1. Open `public_html/public/`
2. Move every file and folder inside it up into `public_html/`
3. Delete the now-empty `public/` folder
4. Confirm `public_html/index.php` exists
5. Confirm `public_html/assets/` exists

## Environment File

Create `.env.local` one level above `public_html/` with your live Hostinger database values:

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
- If you change `.env.local`, refresh the page. You usually do not need a special restart step for standard PHP page loads on shared hosting.

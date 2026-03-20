# Hostinger Deployment Notes

This starter is organized with a `public/` web root.

## Recommended Upload Layout

On Hostinger, place files like this:

```text
home/
  includes/
  sql/
  public_html/
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
    assets/
```

## How To Upload

1. Upload everything inside `public/` into `public_html/`
2. Upload `includes/` next to `public_html/`
3. Upload `sql/` next to `public_html/`

The page files use `dirname(__DIR__) . '/includes/...';`, so `includes/` must sit one level above `public_html/`.

## Database Setup

1. Create a MySQL database in Hostinger
2. Import [schema.sql](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql/schema.sql)
3. Import [seed.sql](/Users/daniel.maldonado1/Documents/Code/Bible%20App/sql/seed.sql)
4. Update [config.php](/Users/daniel.maldonado1/Documents/Code/Bible%20App/includes/config.php) with Hostinger DB credentials

## Important Note

The current login and register pages use a temporary session-based demo flow so the app can be previewed before real database auth is wired in.

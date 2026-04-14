# Good News Bible Project Plan

## 1. Core Direction

Build a mobile-friendly Bible study app focused on:

- Studying the Word of God
- Celebrating and saving meaningful verses
- Bookmarking and sharing passages
- Bible study notes and study plans
- A yearly planner for spiritual goals
- Personal organization tools
- Community life and church-connected events

Important constraint:

- Do not use Node.js
- Use `HTML + CSS + JavaScript` for the frontend
- Use `PHP` for the backend on Hostinger
- Use `MySQL` on Hostinger for data storage

Pure HTML alone is not enough for login, bookmarks, saved notes, or database work. The practical stack is:

- Frontend: `HTML, CSS, JavaScript`
- Backend: `PHP`
- Database: `MySQL`

## 2. Recommended Stack

### Frontend

- `HTML5`
- `CSS3`
- `Vanilla JavaScript`
- `Font Awesome` or locally stored SVG icons for large icons

### Backend

- `PHP 8+`
- `PDO` for secure MySQL queries
- Session-based authentication

### Database

- `MySQL` on Hostinger

### Hosting Fit

Hostinger is a strong fit for:

- PHP websites
- MySQL databases
- Simple deployment by file upload or Git

## 3. Visual Style

Theme: warm, bold, American western-inspired, welcoming, strong typography.

### Design Language

- Warm earth colors
- Cream, saddle brown, clay red, dusty gold, deep navy accents
- Big bold headings
- Large icons
- Card-based layout
- Rounded but sturdy buttons
- Wood/leather/parchment inspired textures used lightly

### Suggested Palette

- Background: `#F6E9D3`
- Surface: `#FFF8EE`
- Primary brown: `#6B3E26`
- Accent rust: `#B8572E`
- Gold: `#D6A64F`
- Deep blue: `#23395B`
- Text dark: `#2A211C`

### Typography Direction

- Headings: bold western or slab-serif style
- Body: clean readable serif or sans-serif

Suggested pairing:

- Heading: `Bree Serif`, `Roboto Slab`, or `Merriweather Bold`
- Body: `Source Sans 3` or `Open Sans`

## 4. Main User Experience

### Public Pages

1. Landing page
2. About / mission page
3. Sign up
4. Login
5. Contact / prayer request page

### App Pages

1. Dashboard
2. Daily verse page
3. Bible reader page
4. Verse bookmarks page
5. Notes / study journal page
6. Bible study plans page
7. Yearly planner page
 8. Community events page
 9. Prayer list / goals page
 10. User profile page
 11. Settings page

### Admin Pages

1. Admin login
2. Verse management
3. Reading plan management
4. Featured devotion management
5. User management

## 5. Feature Breakdown

### A. Easy Authentication

Keep auth simple and reliable:

- Email + password sign up
- Login
- Logout
- Forgot password
- Profile edit

Recommended approach:

- PHP sessions
- Password hashing with `password_hash()`
- Password verification with `password_verify()`
- Email verification can be added in phase 2

### B. Landing Page

Purpose:

- Welcome users
- Explain the app mission
- Encourage sign up
- Show featured verse and key tools

Sections:

- Hero section with bold western visual style
- “Study the Word” message
- Feature cards
- Daily featured verse
- Testimony or mission section
- Call to action

### C. Bible Reader

Core capabilities:

- Browse by book, chapter, verse
- Search verses
- Highlight favorite verses
- Copy verse text
- Bookmark passage
- Share passage

Note:

Bible text licensing matters. Use a public-domain translation if needed, such as `KJV`, or use licensed content correctly.

### D. Bookmark and Share Passages

Users should be able to:

- Save passages
- Add a tag or category
- Add a short note
- Share a verse as text
- Share a verse card image later in phase 2

### E. Bible Study Tools

Include:

- Personal notes per verse or chapter
- Study topics
- Scripture memory list
- Reading history
- Study plans

### F. Yearly Planner

This should feel practical and spiritual:

- Set yearly scripture goals
- Set monthly study goals
- Track reading progress
- Add church events
- Add reminders for prayer, fasting, devotion, and family study

### G. Organizational Tools

Include:

- Prayer journal
- Favorite verses list
- Topic collections
- Notes organizer
- Reading streaks
- Calendar planner

### H. Community and Events

The app should also support community connection and shared events.

Primary use cases:

- Church services
- Dev meetups
- Prayer meetings
- Zoom calls
- Bible study groups
- Pot lucks
- Career events
- Celebrations
- Education or training sessions

Community capabilities:

- View a shared event feed
- Filter by event type
- See upcoming and past events
- RSVP or mark interest
- Add location or Zoom link
- Save events to a personal planner
- Allow approved leaders to create and edit events

## 6. Recommended Navigation

### Mobile Bottom Navigation

Use large icons and labels:

1. Home
2. Bible
 3. Community
 4. Planner
 5. Saved

### Desktop Navigation

- Top navigation bar
- Side menu inside the app dashboard

## 7. Suggested Database Schema

### `users`

- `id`
- `name`
- `email`
- `password_hash`
- `role`
- `created_at`
- `updated_at`

### `books`

- `id`
- `name`
- `abbreviation`
- `testament`

### `chapters`

- `id`
- `book_id`
- `chapter_number`

### `verses`

- `id`
- `book_id`
- `chapter_number`
- `verse_number`
- `verse_text`
- `translation`

### `bookmarks`

- `id`
- `user_id`
- `verse_id`
- `note`
- `tag`
- `created_at`

### `study_notes`

- `id`
- `user_id`
- `verse_id`
- `title`
- `content`
- `created_at`
- `updated_at`

### `reading_plans`

- `id`
- `title`
- `description`
- `duration_days`
- `created_at`

### `user_reading_progress`

- `id`
- `user_id`
- `reading_plan_id`
- `day_number`
- `completed_at`

### `yearly_goals`

- `id`
- `user_id`
- `year`
- `goal_title`
- `goal_type`
- `target_value`
- `current_value`
- `status`

### `planner_events`

- `id`
- `user_id`
- `title`
- `description`
- `event_date`
- `event_type`
- `created_at`

### `community_events`

- `id`
- `created_by_user_id`
- `title`
- `description`
- `event_type`
- `visibility`
- `location_name`
- `location_address`
- `meeting_url`
- `start_at`
- `end_at`
- `is_featured`
- `status`
- `created_at`
- `updated_at`

### `community_event_rsvps`

- `id`
- `community_event_id`
- `user_id`
- `response`
- `created_at`
- `updated_at`

### `community_event_categories`

- `id`
- `slug`
- `label`
- `icon`
- `color`

### `prayer_entries`

- `id`
- `user_id`
- `title`
- `details`
- `status`
- `created_at`

## 8. Simple App Structure

Recommended project structure:

```text
/public
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
  assets/
    css/
    js/
    images/
    icons/

/includes
  config.php
  db.php
  auth.php
  header.php
  footer.php
  helpers.php

/admin
  index.php
  verses.php
  users.php
  plans.php

/sql
  schema.sql
  seed.sql
```

## 9. Best Auth Plan for This App

For simplicity and Hostinger compatibility:

### Phase 1 Auth

- Register with name, email, password
- Login with email and password
- PHP session for logged-in state
- “Remember me” can wait until phase 2

### Security Essentials

- Use prepared statements with PDO
- Hash passwords
- Validate form input
- Escape output
- Add CSRF tokens for forms
- Rate-limit repeated login attempts if possible

## 10. Landing Page Plan

### Hero Section

Headline example:

`Study the Word. Save the Verses. Walk with Purpose.`

Subtext:

`A bold and beautiful Bible app for reading Scripture, organizing your study life, and keeping God’s Word close every day.`

Buttons:

- Start Studying
- Create Free Account

### Landing Page Sections

1. Hero banner
2. Featured verse card
3. Main features with large icons
4. Study planner preview
5. Testimony / mission section
6. Mobile app style preview
7. Footer with scripture and links

## 11. Mobile-First Design Rules

- Build for phones first
- Use large tap targets
- Use strong contrast
- Keep menus simple
- Use fixed bottom navigation on mobile
- Keep verse cards readable with generous spacing

Recommended breakpoints:

- Mobile: under `768px`
- Tablet: `768px` to `1024px`
- Desktop: above `1024px`

## 12. MVP Scope

Build this first:

1. Landing page
2. Register / login
3. Dashboard
4. Bible reader
5. Bookmark verses
6. Add study notes
7. Yearly planner basics
8. Profile/settings

This is enough for a real first version.

## 13. Phase 2 Features

Add later:

- Verse image sharing
- Email verification
- Password reset email
- Reading streaks
- Prayer reminders
- Topic-based study collections
- Admin content tools
- Audio Bible support

## 14. Recommended Build Order

### Phase 1: Foundation

- Set up Hostinger database
- Create PHP project structure
- Build config and DB connection
- Create schema

### Phase 2: Authentication

- Register
- Login
- Logout
- Protected dashboard

### Phase 3: Main App

- Bible reader
- Search
- Bookmarks
- Notes

### Phase 4: Planner

- Yearly goals
- Calendar events
- Reading plan tracking

### Phase 5: Community

- Shared event feed
- Event categories
- RSVP system
- Leader event management
- Planner integration

### Phase 6: Polish

- Responsive refinement
- Share tools
- Admin tools
- Performance and security checks

## 15. Practical Recommendation

Best technical direction for this project:

- Use `PHP + MySQL + HTML + CSS + JavaScript`
- Keep the first version server-rendered
- Avoid frameworks at the start
- Use clean reusable includes for layout
- Build mobile-first from day one

This keeps the app:

- Easier to host on Hostinger
- Easier to maintain
- Easier to secure than an overcomplicated stack

## 16. Next Step Recommendation

Immediate next deliverables:

1. Create the folder structure
2. Create `schema.sql`
3. Build the landing page
4. Build register/login
5. Build dashboard

If you want, the next step can be building the actual starter files for this stack inside this folder.

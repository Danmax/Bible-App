ALTER TABLE community_events
    ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL AFTER visibility;

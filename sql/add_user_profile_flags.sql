ALTER TABLE users
    ADD COLUMN IF NOT EXISTS primary_flag VARCHAR(24) NULL AFTER avatar_url,
    ADD COLUMN IF NOT EXISTS secondary_flag VARCHAR(24) NULL AFTER primary_flag;

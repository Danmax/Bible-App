ALTER TABLE public_radio_stations
    ADD COLUMN IF NOT EXISTS youtube_playlist_id VARCHAR(80) NULL AFTER listen_url;

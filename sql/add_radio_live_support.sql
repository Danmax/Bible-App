ALTER TABLE public_radio_stations
    ADD COLUMN IF NOT EXISTS youtube_live_video_id VARCHAR(30) NULL DEFAULT NULL AFTER youtube_playlist_id,
    ADD COLUMN IF NOT EXISTS is_live TINYINT(1) NOT NULL DEFAULT 0 AFTER youtube_live_video_id;

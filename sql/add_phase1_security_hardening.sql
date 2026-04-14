ALTER TABLE friend_invites
    MODIFY COLUMN invite_token VARCHAR(48) NULL,
    ADD COLUMN invite_token_hash CHAR(64) NULL AFTER recipient_email;

UPDATE friend_invites
SET invite_token_hash = SHA2(invite_token, 256)
WHERE invite_token_hash IS NULL
    AND invite_token IS NOT NULL;

ALTER TABLE friend_invites
    MODIFY COLUMN invite_token_hash CHAR(64) NOT NULL,
    ADD UNIQUE KEY unique_friend_invite_token_hash (invite_token_hash);

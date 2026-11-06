-- content_slots.origin was enum('normal','fastlane'); baseline/testposts hebben meer waarden nodig.
ALTER TABLE content_slots
  MODIFY COLUMN origin VARCHAR(16) NOT NULL DEFAULT 'normal';

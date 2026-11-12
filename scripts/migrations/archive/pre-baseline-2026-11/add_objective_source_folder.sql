-- Koppel doelstelling aan Sources-map voor gerichte CE-02 research
ALTER TABLE objectives ADD COLUMN IF NOT EXISTS source_folder_id CHAR(36) NULL;

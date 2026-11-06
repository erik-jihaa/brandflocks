-- Document-chunks: utf8mb4 voor bullets, emoji en speciale tekens uit PDF's
ALTER TABLE source_document_chunks
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

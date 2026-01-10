-- Migration: Add file attachment support to svagenda_comments
-- Created: 2026-01-10
-- Purpose: Allow file uploads in agenda item comments

-- Add file attachment field to svagenda_comments table
ALTER TABLE svagenda_comments
ADD COLUMN attachment_filename VARCHAR(500) DEFAULT NULL AFTER comment_text,
ADD COLUMN attachment_original_name VARCHAR(255) DEFAULT NULL AFTER attachment_filename,
ADD COLUMN attachment_size INT DEFAULT NULL AFTER attachment_original_name,
ADD COLUMN attachment_mime_type VARCHAR(100) DEFAULT NULL AFTER attachment_size,
ADD INDEX idx_attachment (attachment_filename);

-- Note: Files will be stored in uploads/ directory with format:
-- {meeting_id}-{member_id}-{original_filename}

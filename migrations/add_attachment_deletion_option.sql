-- Migration: Add deletion_option to file attachments
-- Created: 2026-01-10
-- Purpose: Allow users to specify when/how uploaded files should be deleted

ALTER TABLE svagenda_comments
ADD COLUMN attachment_deletion_option ENUM('after_meeting', 'after_approval', 'manual', 'include_in_protocol') DEFAULT 'manual' AFTER attachment_mime_type;

ALTER TABLE svagenda_live_comments
ADD COLUMN attachment_deletion_option ENUM('after_meeting', 'after_approval', 'manual', 'include_in_protocol') DEFAULT 'manual' AFTER attachment_mime_type;

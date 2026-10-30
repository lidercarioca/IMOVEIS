-- Migration: Add missing columns to scheduled_jobs table for JobScheduler compatibility
-- Description: Adds columns required by JobScheduler.php to align with its usage
-- Version: 1.1
-- Date: 2024

-- Add job_type column (used by JobScheduler for job identification)
ALTER TABLE scheduled_jobs ADD COLUMN job_type VARCHAR(255) NULL;

-- Add cron_expression column (cron expression for scheduling)
ALTER TABLE scheduled_jobs ADD COLUMN cron_expression VARCHAR(100) NULL;

-- Add job_data column (JSON data for job parameters)
ALTER TABLE scheduled_jobs ADD COLUMN job_data JSON NULL;

-- Add user_id column (user associated with the job)
ALTER TABLE scheduled_jobs ADD COLUMN user_id INT(11) NULL;

-- Add active column (job active status, defaults to 1)
ALTER TABLE scheduled_jobs ADD COLUMN active TINYINT(1) DEFAULT 1;

-- Add run_count column (number of times job has run)
ALTER TABLE scheduled_jobs ADD COLUMN run_count INT(11) DEFAULT 0;

-- Add failure_count column (number of failures)
ALTER TABLE scheduled_jobs ADD COLUMN failure_count INT(11) DEFAULT 0;

-- Add last_failure column (timestamp of last failure)
ALTER TABLE scheduled_jobs ADD COLUMN last_failure TIMESTAMP NULL;

-- Add index for active and next_run if not exists (for performance)
-- Note: idx_enabled_next_run already exists, but for active
-- Since active is new, add index
CREATE INDEX IF NOT EXISTS idx_active_next_run ON scheduled_jobs (active, next_run);
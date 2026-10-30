-- Migration: Add event_hash column and index to webhook_events for OLX replay protection
ALTER TABLE webhook_events
  ADD COLUMN event_hash VARCHAR(64) NULL AFTER entity_id;

ALTER TABLE webhook_events
  ADD INDEX idx_webhook_events_tenant_event_hash (tenant_id, event_hash);

UPDATE webhook_events
  SET event_hash = JSON_UNQUOTE(JSON_EXTRACT(data, '$.event_hash'))
  WHERE event_hash IS NULL
    AND JSON_EXTRACT(data, '$.event_hash') IS NOT NULL;

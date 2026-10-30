<?php

/**
 * ============================================================================
 * ⚠️  DEPRECATED — UserModel MIGRATED TO LEGACY
 * ============================================================================
 * 
 * This file is maintained for BACKWARD COMPATIBILITY ONLY.
 * 
 * Status: LEGACY - DO NOT USE IN NEW CODE
 * Migration Date: 2026-07-17
 * Deprecation Ends: 2026-09-17 (60-day window)
 * 
 * The UserModel class has been moved to app/legacy/UserModel.php
 * This is a deprecated stub that will be removed.
 * 
 * MIGRATION PATH:
 * 
 * For reads:
 *   OLD: $model->getUserById($id);
 *   NEW: $repo->findById($id, $tenantId);
 * 
 * For writes:
 *   OLD: $model->createUser($data);
 *   NEW: $service->createUser($tenantId, $data);
 * 
 * Affected Files: 0 (no active consumers found)
 * Last Checked: 2026-07-17
 * 
 * ============================================================================
 */

namespace App\Models;

// Load from legacy location for backward compatibility
require_once __DIR__ . '/../legacy/UserModel.php';

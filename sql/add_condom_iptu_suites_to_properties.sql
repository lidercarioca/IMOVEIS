-- Migration: add condominium, iptu, suites to properties
ALTER TABLE properties
  ADD COLUMN condominium DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN iptu DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN suites INT(11) DEFAULT NULL;

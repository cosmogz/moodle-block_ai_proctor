-- AI Proctor System - Database Schema for Licensing and Analytics

-- Usage tracking table
CREATE TABLE mdl_block_ai_proctor_usage (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    institution_code VARCHAR(50) NOT NULL,
    course_id BIGINT(10) NOT NULL,
    user_hash VARCHAR(64) NOT NULL, -- Anonymized user ID
    session_duration INT(10) NOT NULL, -- Duration in seconds
    violations_detected INT(5) NOT NULL DEFAULT 0,
    exam_type VARCHAR(50) DEFAULT 'standard',
    timestamp BIGINT(10) NOT NULL,
    plugin_version VARCHAR(20),
    PRIMARY KEY (id),
    INDEX idx_institution_timestamp (institution_code, timestamp),
    INDEX idx_course_timestamp (course_id, timestamp)
);

-- Analytics queue for batch processing
CREATE TABLE mdl_block_ai_proctor_analytics_queue (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    data LONGTEXT NOT NULL, -- JSON encoded analytics data
    queued BIGINT(10) NOT NULL, -- When queued
    sent BIGINT(10) NOT NULL DEFAULT 0, -- When sent (0 = pending)
    retries INT(3) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_sent (sent),
    INDEX idx_queued (queued)
);

-- License information
CREATE TABLE mdl_block_ai_proctor_license (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    institution_code VARCHAR(50) NOT NULL UNIQUE,
    license_key TEXT NOT NULL,
    license_type VARCHAR(20) NOT NULL, -- basic, standard, premium, enterprise
    max_students INT(10) NOT NULL DEFAULT 0,
    valid_from BIGINT(10) NOT NULL,
    valid_until BIGINT(10) NOT NULL,
    contact_email VARCHAR(255),
    billing_info TEXT, -- JSON encoded billing information
    created BIGINT(10) NOT NULL,
    modified BIGINT(10) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_institution (institution_code),
    INDEX idx_validity (valid_from, valid_until)
);

-- Revenue tracking
CREATE TABLE mdl_block_ai_proctor_revenue (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    institution_code VARCHAR(50) NOT NULL,
    billing_period_start BIGINT(10) NOT NULL,
    billing_period_end BIGINT(10) NOT NULL,
    total_sessions INT(10) NOT NULL DEFAULT 0,
    unique_students INT(10) NOT NULL DEFAULT 0,
    total_revenue DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    invoice_generated TINYINT(1) NOT NULL DEFAULT 0,
    invoice_paid TINYINT(1) NOT NULL DEFAULT 0,
    created BIGINT(10) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_institution_period (institution_code, billing_period_start),
    INDEX idx_invoice_status (invoice_generated, invoice_paid)
);

-- Security violations and compliance
CREATE TABLE mdl_block_ai_proctor_security (
    id BIGINT(10) NOT NULL AUTO_INCREMENT,
    institution_code VARCHAR(50) NOT NULL,
    violation_type VARCHAR(50) NOT NULL, -- file_tampering, license_exceeded, etc.
    severity VARCHAR(20) NOT NULL, -- low, medium, high, critical
    details TEXT,
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    timestamp BIGINT(10) NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_institution_type (institution_code, violation_type),
    INDEX idx_severity (severity, resolved)
);

-- Insert default configuration
INSERT INTO mdl_config_plugins (plugin, name, value) VALUES
('block_ai_proctor', 'license_version', '1.0'),
('block_ai_proctor', 'analytics_enabled', '1'),
('block_ai_proctor', 'license_check_frequency', '86400'), -- 24 hours
('block_ai_proctor', 'batch_upload_size', '100'),
('block_ai_proctor', 'revenue_model', 'per_student'); -- per_student, per_session, flat_rate
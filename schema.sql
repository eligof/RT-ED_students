-- schema.sql
-- Full MySQL schema for the Real Time College cybersecurity aptitude exam system.
-- Covers every table the eventual system needs, so no later migration is required.
-- Charset: utf8mb4 throughout. Engine: InnoDB.
--
-- Only `leads` and `exam_access_codes` are actively used in the CRM integration phase.
-- `exam_questions`, `exam_results`, and `exam_answers` are defined now so content and
-- later features can be layered in without schema changes.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Leads table.
-- Assume this may already exist in the host CRM. CREATE IF NOT EXISTS keeps the
-- import script non-destructive. If your CRM uses different table/column names,
-- leave this block untouched and point config.php at the existing table via the
-- TABLE_LEADS and LEAD_*_COLUMN constants.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leads (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255),
    email      VARCHAR(255) UNIQUE,
    phone      VARCHAR(50),
    status     VARCHAR(50) DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Access codes for exam entry.
-- The code is stored as a bcrypt hash (password_hash output, up to 255 chars).
-- Plaintext is shown to the admin once at generation time and is never persisted.
-- used_at is set when the student consumes the code during login (future phase),
-- enforcing one-time use.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_access_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    lead_id    INT NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    INDEX idx_lead_expires (lead_id, expires_at),
    INDEX idx_lead         (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Question bank. The full system samples QUESTIONS_PER_EXAM rows at random per
-- attempt. Populated later with ~100 questions across several categories.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_questions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    category       VARCHAR(100) NOT NULL,
    question_text  TEXT NOT NULL,
    option_a       TEXT NOT NULL,
    option_b       TEXT NOT NULL,
    option_c       TEXT NOT NULL,
    option_d       TEXT NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    explanation    TEXT,
    hint           TEXT NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Exam attempts / aggregate results. tab_switches and tab_switch_duration_seconds
-- are populated by the client-side proctoring layer in the future phase.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_results (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    lead_id                     INT NOT NULL,
    code_id                     INT NOT NULL,
    score                       INT DEFAULT 0,
    total_questions             INT DEFAULT 20,
    started_at                  DATETIME NOT NULL,
    finished_at                 DATETIME NULL,
    tab_switches                INT DEFAULT 0,
    tab_switch_duration_seconds INT DEFAULT 0,
    INDEX idx_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Per-question detail for each attempt.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_answers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    result_id           INT NOT NULL,
    question_id         INT NOT NULL,
    chosen_answer       CHAR(1) NULL,
    is_correct          BOOLEAN NULL,
    time_taken_seconds  INT DEFAULT 0,
    INDEX idx_result (result_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

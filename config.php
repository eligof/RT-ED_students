<?php
/**
 * config.php
 * Central configuration for the Real Time College aptitude exam system.
 *
 * Every tunable lives here: DB credentials, exam behavior, table/column names,
 * admin auth, and helper functions used by the admin endpoints. Edit the
 * CHANGE_ME values and the table/column mappings to match your environment,
 * then require this file from every entry point.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Database connection
// Edit the fallback values directly, or set matching env vars (useful for
// Docker / Codespaces / CI).
// ---------------------------------------------------------------------------
define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME',     getenv('DB_NAME')     ?: 'rt_exam');
define('DB_USER',     getenv('DB_USER')     ?: 'rt_exam');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'CHANGE_ME');

// ---------------------------------------------------------------------------
// Exam behavior
// ---------------------------------------------------------------------------
define('CODE_LENGTH',         8);
define('CODE_EXPIRY_HOURS',   48);
define('QUESTIONS_PER_EXAM',  20);

// Characters used when minting codes. 0/O/1/I/L are excluded on purpose —
// they are routinely misread when codes are dictated over the phone or copied
// from printouts.
define('CODE_CHARSET', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789');

// ---------------------------------------------------------------------------
// Table names. Override if integrating with an existing CRM schema.
// ---------------------------------------------------------------------------
define('TABLE_LEADS',     'leads');
define('TABLE_CODES',     'exam_access_codes');
define('TABLE_QUESTIONS', 'exam_questions');
define('TABLE_RESULTS',   'exam_results');
define('TABLE_ANSWERS',   'exam_answers');

// ---------------------------------------------------------------------------
// Lead column mapping. Override if the host CRM uses different column names.
// ---------------------------------------------------------------------------
define('LEAD_ID_COLUMN',    'id');
define('LEAD_EMAIL_COLUMN', 'email');
define('LEAD_NAME_COLUMN',  'name');
define('LEAD_PHONE_COLUMN', 'phone');

// ---------------------------------------------------------------------------
// Admin HTTP Basic auth. Change ADMIN_PASS before deploying.
// Fallbacks below; env vars ADMIN_USER / ADMIN_PASS override when set.
// ---------------------------------------------------------------------------
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'CHANGE_ME');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return a lazily-initialised PDO connection. utf8mb4 from both sides, strict
 * error mode so exceptions bubble up, associative fetches by default.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/**
 * Generate a cryptographically secure access code of CODE_LENGTH characters
 * drawn from CODE_CHARSET. Uses random_int() (CSPRNG) so the codes are not
 * predictable from prior output.
 */
function generate_code(): string
{
    $charset = CODE_CHARSET;
    $max     = strlen($charset) - 1;
    $code    = '';
    for ($i = 0; $i < CODE_LENGTH; $i++) {
        $code .= $charset[random_int(0, $max)];
    }
    return $code;
}

/**
 * Send a generic error response as JSON and exit. Details never reach the
 * client — they are funneled to log_error() on the server side.
 */
function json_error(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Server-side error logger. Keep internal details out of client responses.
 */
function log_error(string $msg): void
{
    error_log('[rt-exam] ' . $msg);
}

/**
 * Guard an endpoint with HTTP Basic authentication. Sends a 401 challenge
 * if credentials are missing or wrong. Comparison uses hash_equals() to avoid
 * timing side-channels on the username/password check.
 */
function require_basic_auth(): void
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW']   ?? '';

    $user_ok = hash_equals(ADMIN_USER, $user);
    $pass_ok = hash_equals(ADMIN_PASS, $pass);

    if (!$user_ok || !$pass_ok) {
        header('WWW-Authenticate: Basic realm="RT Exam Admin", charset="UTF-8"');
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
}

/**
 * Return the current session CSRF token, minting one if absent. Callers must
 * ensure session_start() has run first.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Verify a submitted CSRF token against the session token. On mismatch, emit a
 * 403 and exit so callers do not have to repeat the boilerplate.
 */
function require_csrf_token(string $submitted): void
{
    $expected = $_SESSION['csrf'] ?? '';
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        json_error(403, 'Invalid CSRF token');
    }
}

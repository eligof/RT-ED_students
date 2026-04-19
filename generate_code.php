<?php
/**
 * generate_code.php
 * Admin-only JSON endpoint that mints a one-time exam access code for a lead.
 *
 * Flow: verify Basic auth, enforce POST + CSRF, validate lead_id exists,
 * generate a CSPRNG code, hash it with password_hash(), insert into the codes
 * table, and return the plaintext code + expiry to the caller. The plaintext
 * is returned ONCE ONLY — it is never stored, so the admin must copy it
 * immediately.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

require_basic_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_csrf_token((string)($_POST['csrf'] ?? ''));

$lead_id = (int)($_POST['lead_id'] ?? 0);
if ($lead_id <= 0) {
    json_error(400, 'Invalid lead_id');
}

try {
    $pdo = db();

    // Table and column identifiers come from trusted constants in config.php,
    // not user input, so interpolating them into the SQL is safe. The value
    // itself is bound as a parameter.
    $sql = sprintf(
        'SELECT %s FROM %s WHERE %s = :id LIMIT 1',
        LEAD_ID_COLUMN,
        TABLE_LEADS,
        LEAD_ID_COLUMN
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $lead_id]);
    if ($stmt->fetch() === false) {
        json_error(404, 'Lead not found');
    }

    $code       = generate_code();
    $code_hash  = password_hash($code, PASSWORD_DEFAULT);
    $expires_at = gmdate('Y-m-d H:i:s', time() + CODE_EXPIRY_HOURS * 3600);

    $insert = sprintf(
        'INSERT INTO %s (lead_id, code_hash, expires_at) VALUES (:lead_id, :code_hash, :expires_at)',
        TABLE_CODES
    );
    $stmt = $pdo->prepare($insert);
    $stmt->execute([
        ':lead_id'    => $lead_id,
        ':code_hash'  => $code_hash,
        ':expires_at' => $expires_at,
    ]);

    echo json_encode([
        'code'       => $code,
        'expires_at' => $expires_at,
        'lead_id'    => $lead_id,
    ]);
} catch (Throwable $e) {
    log_error('generate_code: ' . $e->getMessage());
    json_error(500, 'Server error');
}

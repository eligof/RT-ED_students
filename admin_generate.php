<?php
/**
 * admin_generate.php
 * Minimal admin UI for generating exam access codes.
 *
 * Protected by the same HTTP Basic auth as generate_code.php. Renders a form
 * that POSTs lead_id + CSRF token to generate_code.php via fetch(), then shows
 * the returned plaintext code. No styling beyond enough to be readable.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

session_start();
require_basic_auth();

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RT Exam — Generate Access Code</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.3rem; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input[type=number] { width: 100%; padding: 0.5rem; font-size: 1rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.6rem 1.2rem; font-size: 1rem; cursor: pointer; }
        #result { margin-top: 1.5rem; padding: 1rem; border: 1px solid #ccc; border-radius: 4px; display: none; }
        #result.ok    { background: #eefbe9; border-color: #7ac77a; }
        #result.error { background: #fbecec; border-color: #c77a7a; }
        .code { font-family: ui-monospace, monospace; font-size: 1.4rem; letter-spacing: 0.1em; }
        .note { color: #666; font-size: 0.85rem; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <h1>Generate Exam Access Code</h1>
    <p>Enter a lead ID to mint a one-time, <?= (int)CODE_EXPIRY_HOURS ?>-hour access code.
       The plaintext code is shown once only — copy it immediately.</p>

    <form id="gen-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label for="lead_id">Lead ID</label>
        <input type="number" id="lead_id" name="lead_id" min="1" required>
        <button type="submit">Generate code</button>
    </form>

    <div id="result" aria-live="polite"></div>

    <script>
        const form   = document.getElementById('gen-form');
        const result = document.getElementById('result');

        form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            result.style.display = 'none';
            result.className = '';

            const body = new FormData(form);
            try {
                const res  = await fetch('generate_code.php', { method: 'POST', body });
                const data = await res.json();

                if (res.ok && data.code) {
                    result.className = 'ok';
                    result.innerHTML =
                        '<div>Code for lead #' + Number(data.lead_id) + ':</div>' +
                        '<div class="code">' + data.code + '</div>' +
                        '<div class="note">Expires at (UTC): ' + data.expires_at + '</div>' +
                        '<div class="note">This code cannot be retrieved later — only the hash is stored.</div>';
                } else {
                    result.className = 'error';
                    result.textContent = 'Error: ' + (data.error || ('HTTP ' + res.status));
                }
            } catch (e) {
                result.className = 'error';
                result.textContent = 'Network error: ' + e.message;
            }
            result.style.display = 'block';
        });
    </script>
</body>
</html>

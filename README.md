# RT-ED Cybersecurity Aptitude Exam

Web-based aptitude exam used by Real Time College ([rt-ed.co.il](https://rt-ed.co.il))
to screen prospective information security students. The exam tests reasoning ability
and general tech literacy — not prior security knowledge.

This repository currently contains the **CRM integration layer** only. Later phases
will add the student login, the exam UI, the question bank content, tab-switch
tracking, and the results dashboard. The database schema is already complete, so no
migration will be required when those phases land.

## What is included

| File                 | Purpose                                                                 |
| -------------------- | ----------------------------------------------------------------------- |
| `schema.sql`         | Full MySQL schema for every table the system will eventually need.      |
| `config.php`         | All tunables (DB, code length, expiry, table/column names, admin auth). |
| `generate_code.php`  | Admin-only JSON endpoint that mints an access code for a given lead.    |
| `admin_generate.php` | Minimal form that calls the endpoint and displays the generated code.   |

## Try it live in GitHub Codespaces (no install)

The repo ships with a `.devcontainer/` that provisions PHP 8.2 + MySQL 8 and
auto-imports the schema. Open a Codespace and you get a public HTTPS URL to the
admin form in ~1 minute.

1. On GitHub: **Code → Codespaces → Create codespace on main**.
2. Wait for the container to build. A terminal tab shows the setup script
   installing the MySQL client, importing `schema.sql`, and seeding a demo lead.
3. Codespaces pops a notification for port `8000` — click **Open in Browser**,
   or open the **Ports** panel and click the globe icon.
4. Log in to the Basic-auth prompt with **`admin`** / **`demo_admin`**.
5. Enter lead ID **`1`** (pre-seeded) and press *Generate code*.

Credentials used by the Codespace are defined as env vars in
`.devcontainer/docker-compose.yml` (`demo_pass` / `demo_admin`). They never
touch `config.php` on disk, so committing from inside the Codespace will not
leak them.

## Requirements

- PHP 7.4 or newer with the PDO MySQL driver enabled
- MySQL 5.7+ / MariaDB 10.2+
- HTTPS-capable web server (serve this behind TLS in production)

## Setup

1. **Create the database**
   ```sql
   CREATE DATABASE rt_exam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import the schema**
   ```sh
   mysql -u root -p rt_exam < schema.sql
   ```
   `leads` is created with `CREATE TABLE IF NOT EXISTS`, so importing against a CRM
   database that already has a `leads` table is safe.

3. **Edit `config.php`** — at minimum change every `CHANGE_ME` value:
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   - `ADMIN_USER`, `ADMIN_PASS`

   If integrating with an existing CRM, also adjust:
   - `TABLE_LEADS` and the `LEAD_*_COLUMN` constants to point at the existing table
     and column names.
   - `CODE_LENGTH`, `CODE_EXPIRY_HOURS`, `QUESTIONS_PER_EXAM` if the defaults
     (8 / 48h / 20) need tuning.

4. **Upload the files** to a PHP-capable directory on your server.

5. **Use it.** Open `admin_generate.php` in a browser, pass the HTTP Basic prompt
   with the `ADMIN_USER` / `ADMIN_PASS` you configured, enter a `lead_id`, and press
   *Generate code*. The code is displayed once — **copy it immediately**, because only
   the bcrypt hash is stored in the database.

## Security notes

- The access code is stored as a bcrypt hash (`password_hash()` / `password_verify()`).
  Plaintext is returned exactly once at generation time.
- Both `admin_generate.php` and `generate_code.php` require HTTP Basic credentials
  defined in `config.php`. Always serve this application over HTTPS so those
  credentials are not sent in the clear.
- The admin form carries a session-backed CSRF token that `generate_code.php`
  verifies on every submission.
- All SQL uses prepared statements with bound parameters. Table and column names
  are interpolated from trusted constants in `config.php` — never from user input.
- `lead_id` is cast to `int` before being used in any query.
- Client errors are generic; details are sent to `error_log` server-side via
  `log_error()`.
- Codes are generated with `random_int()` over a 31-character alphabet that
  excludes visually ambiguous characters (`0/O/1/I/L`) to prevent transcription
  mistakes when codes are dictated or emailed.
- Consider adding a reverse-proxy IP allowlist or VPN gate in front of
  `admin_generate.php` and `generate_code.php` in production.
- Rotate `ADMIN_PASS` regularly.

## Manual verification

After installing, confirm the integration works end-to-end:

```sql
INSERT INTO leads (name, email, phone) VALUES ('Test Lead', 'test@example.com', '+972-0-0000000');
```

1. Visit `admin_generate.php`, authenticate, submit the new lead's `id` → the page
   shows an 8-character code and an expiry ~48h in the future.
2. `SELECT lead_id, code_hash, expires_at FROM exam_access_codes;` — one row, hash
   begins with `$2y$`.
3. Submit a non-existent `lead_id` → response is `404 {"error":"Lead not found"}`.
4. POST to `generate_code.php` without Basic auth → `401`.
5. POST with a bad `csrf` value → `403`.
6. GET `generate_code.php` → `405`.

## Roadmap (future phases, not in this build)

- Student login page (email + access code), which will call `password_verify()`
  against `exam_access_codes.code_hash` and mark `used_at` on success.
- `exam.php` — serves `QUESTIONS_PER_EXAM` randomly sampled questions.
- Tab-switch tracking via the Page Visibility API.
- Results dashboard.
- Automated email delivery of codes (for now, codes are sent manually).
- Content population of `exam_questions` (~100 items across several categories).

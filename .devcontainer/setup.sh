#!/usr/bin/env bash
# setup.sh — runs once after the dev container is created.
# The Dockerfile already installs default-mysql-client and pdo_mysql/mysqli,
# so this script only has to wait for MySQL to be ready, import the schema,
# and seed a demo lead.

set -euo pipefail

DB_HOST="${DB_HOST:-mysql}"
DB_USER="${DB_USER:-rt_exam}"
DB_PASSWORD="${DB_PASSWORD:-demo_pass}"
DB_NAME="${DB_NAME:-rt_exam}"

echo "[setup] waiting for mysql at ${DB_HOST}..."
for _ in {1..60}; do
    if mysqladmin ping -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" --silent 2>/dev/null; then
        break
    fi
    sleep 2
done

echo "[setup] importing schema..."
mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < schema.sql

echo "[setup] seeding demo lead (id 1)..."
mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <<SQL
INSERT IGNORE INTO leads (id, name, email, phone)
VALUES (1, 'Demo Lead', 'demo@rt-ed.co.il', '+972-0-0000000');
SQL

echo "[setup] done."
echo "[setup] admin credentials: ${ADMIN_USER:-admin} / ${ADMIN_PASS:-demo_admin}"
echo "[setup] demo lead_id to try in the form: 1"

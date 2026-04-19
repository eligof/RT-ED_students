#!/usr/bin/env bash
# setup.sh — runs once after the dev container is created.
# Installs mysql-client, imports the schema, and seeds a demo lead.

set -euo pipefail

echo "[setup] installing mysql client..."
sudo apt-get update -y >/dev/null
sudo apt-get install -y --no-install-recommends default-mysql-client >/dev/null

echo "[setup] waiting for mysql to be ready..."
for i in {1..60}; do
    if mysqladmin ping -h mysql -u rt_exam -pdemo_pass --silent 2>/dev/null; then
        break
    fi
    sleep 2
done

echo "[setup] importing schema..."
mysql -h mysql -u rt_exam -pdemo_pass rt_exam < schema.sql

echo "[setup] seeding demo lead (id 1)..."
mysql -h mysql -u rt_exam -pdemo_pass rt_exam <<SQL
INSERT IGNORE INTO leads (id, name, email, phone)
VALUES (1, 'Demo Lead', 'demo@rt-ed.co.il', '+972-0-0000000');
SQL

echo "[setup] done."
echo "[setup] admin credentials: admin / demo_admin"
echo "[setup] demo lead_id to try in the form: 1"

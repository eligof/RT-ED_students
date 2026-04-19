#!/usr/bin/env bash
# start-server.sh — runs on every container start. Ensures the PHP built-in
# web server is running in the background on port 8000.

set -euo pipefail

# Kill any existing PHP server on port 8000 (idempotent restart).
pkill -f "php -S 0.0.0.0:8000" 2>/dev/null || true
sleep 1

mkdir -p /tmp/rt-exam
nohup php -S 0.0.0.0:8000 -t /workspace > /tmp/rt-exam/server.log 2>&1 &

echo "[start] PHP server on :8000 (logs: /tmp/rt-exam/server.log)"

#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

if [[ -f "$ROOT_DIR/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT_DIR/.env"
  set +a
fi

echo "Starting CoachLab at http://${HOST}:${PORT}/coachlab/coachlab.html"
echo "Press Ctrl+C to stop."

cd "$ROOT_DIR"
php -S "${HOST}:${PORT}" -t public_html router.php

#!/usr/bin/env bash

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${project_dir}"

# Run retention cleanup on every cron invocation, even while the waiting chat
# worker from an earlier invocation is still running.
/usr/bin/php artisan conversations:purge-expired --no-interaction --no-ansi >> storage/logs/temporary-chat-cleanup.log 2>&1

# Hostinger invokes this every minute. Keep one worker waiting between runs,
# and exit before the hosting platform's 30-minute cron process limit.
# QUEUE_RETRY_AFTER must exceed --timeout (480 seconds is the app default).
exec /usr/bin/flock -n storage/framework/chat-worker.lock \
    /usr/bin/php artisan queue:work database --queue=chat --sleep=1 \
    --tries=1 --timeout=420 --max-time=1200 --no-interaction --no-ansi \
    >> storage/logs/chat-worker.log 2>&1

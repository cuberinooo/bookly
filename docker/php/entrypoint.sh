#!/bin/bash
set -e

# Install vendors if they don't exist
if [ ! -d "vendor" ]; then
    composer install --no-interaction
fi

# Clear stale Symfony server data
rm -rf /root/.symfony5/publish-pids.json 2>/dev/null

exec "$@"

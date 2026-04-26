#!/bin/bash
set -e

# Install vendors if they don't exist
if [ ! -d "vendor" ]; then
    composer install --no-interaction
fi

exec "$@"

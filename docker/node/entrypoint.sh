#!/bin/bash
set -e

# Change to the root directory where package.json is
cd /app

# If nx doesn't exist in node_modules, we need to install
if [ ! -f "node_modules/.bin/nx" ]; then
    echo "Nx modules not found. Running npm install..."
    npm install
fi

echo "Nx modules found. Starting frontend..."
# Run the command passed via CMD (e.g., nx serve frontend)
exec "$@"

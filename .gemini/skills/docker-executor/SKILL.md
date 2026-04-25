---
name: docker-executor
description: Execute commands inside Docker containers for backend (Symfony) and frontend (Node/NPM) tasks. Use when you need to run migrations, install packages, or execute CLI tools within the project's Docker environment.
---

# Docker Executor

## Overview
This skill provides a standardized way to execute commands inside the project's Docker containers. This is necessary because tools like PHP (for Symfony) and Node (for NPM/Vite) are isolated within containers.

## Service Mapping
Based on the `docker-compose.yml`, use the following container/service names:
- **Backend (PHP/Symfony)**: `backend`
- **Frontend (Node/NPM)**: `frontend`

## Common Workflows

### 1. Backend (Symfony) Commands
Use `docker exec` to run Symfony console commands.

**Example: Running Migrations**
```bash
docker exec booking-app-backend-1 php bin/console doctrine:migrations:migrate --no-interaction
```
*(Note: Container names often follow the pattern `[folder]-[service]-1`. Verify with `docker ps` if unsure.)*

### 2. Frontend (Node/NPM) Commands
Use `docker exec` to install packages or run scripts.

**Example: Installing a Package**
```bash
docker exec booking-app-frontend-1 npm install <package-name>
```

## Guidelines
1. **Verify Container Names**: Use `docker ps` to find the exact name of the running containers before executing.
2. **Path Awareness**: Commands are executed relative to the container's `WORKDIR`. Ensure paths match the internal container structure.
3. **Interactive Commands**: Avoid interactive prompts by using flags like `--no-interaction` or `-y`.

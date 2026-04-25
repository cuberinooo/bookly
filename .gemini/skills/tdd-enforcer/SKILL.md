---
name: tdd-enforcer
description: Enforce Test-Driven Development (TDD) and rigorous testing standards. Use this skill whenever writing new code, modifying existing logic, or fixing bugs to ensure full test coverage and prevent regressions.
---

# TDD Enforcer

This skill mandates a strict "test-first" or "test-alongside" approach for all development tasks.

## Core Mandates

1. **Bug Fixes**: Before fixing a bug, you MUST create a reproduction test case that fails in the current state. The task is only complete when the test passes and the fix is verified.
2. **New Features**: Every new feature or business logic must be accompanied by unit or integration tests.
3. **Refactoring**: When refactoring, existing tests must be run before and after the change to ensure no regressions. If tests don't exist, they must be created before refactoring.
4. **Coverage**: Focus on testing business logic, edge cases, and error paths. Avoid testing simple getters/setters unless they contain logic.

## Workflow

### 1. Research & Reproduce (for bugs)
- Identify the failing logic.
- Create a minimal test case in the appropriate test suite (e.g., `tests/Service/`, `tests/Controller/`).
- Run the test to confirm it fails.

### 2. Implementation
- Apply the code changes.
- Ensure the changes follow project conventions and architectural patterns.

### 3. Validation
- Run the specific test case to confirm it passes.
- Run the full test suite related to the modified area (e.g., `bin/phpunit tests/Service/`) to ensure no regressions.
- If using Docker, use `docker exec [container] bin/phpunit [path]`.

## Test Locations
- **Backend (PHP/Symfony)**: `apps/backend/tests/`
  - Unit tests: `tests/Service/`, `tests/Entity/`
  - Integration/Functional tests: `tests/Controller/`
- **Frontend (TS/Vue)**: `apps/frontend/src/` (alongside components) or `apps/frontend/tests/`

## Testing Tools
- **PHPUnit**: For backend testing.
- **Vitest**: For frontend unit/component testing.
- **Playwright**: For end-to-end testing (in `apps/frontend-e2e/`).

---
name: cross-stack-enums
description: Manage and synchronize enums between the PHP backend and TypeScript frontend using a single source of truth in `shared/enums/`. Use when the user wants to define or use repeated string constants like course frequencies, statuses, or roles across the stack.
---

# Cross-Stack Enums

## Overview
This skill enables a single source of truth for enums used in both the PHP backend (Symfony) and TypeScript frontend (Vue). It ensures consistency by generating the necessary code from JSON definitions.

## Workflow

1.  **Define the Enum**: Create or update a JSON file in `shared/enums/`.
    - Format: `{ "name": "EnumName", "values": { "KEY": "value" } }`
2.  **Sync Enums**: Run the synchronization script to update the codebases.
    ```bash
    node .gemini/skills/cross-stack-enums/scripts/sync-enums.mjs
    ```
3.  **Use in Code**: Refer to the [Usage Guide](references/usage.md) for examples of using these enums in PHP entities and Vue components.

## Key Directories
- **Source of Truth**: `shared/enums/`
- **Backend PHP Enums**: `apps/backend/src/Enum/` (Namespace: `App\Enum`)
- **Frontend TS Enums**: `apps/frontend/src/app/enums/`

## Resources

### scripts/
- `sync-enums.mjs`: Automates the generation of PHP and TypeScript enum files from the JSON definitions in `shared/enums/`.

### references/
- `usage.md`: Provides code snippets and best practices for integrating the generated enums into entities, controllers, and components.

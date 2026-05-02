---
name: component-architect
description: Guides Gemini CLI in creating modular, reusable, and well-architected frontend components. Use this skill when implementing new UI features, refactoring large components (like DashboardView), or establishing design patterns for clean and maintainable code.
---

# Component Architect

This skill enforces modular design patterns and clean architecture in frontend development.

## Core Mandates

### 1. Modularity First
- **Small is Beautiful**: Aim for components under 200 lines. If a component grows larger, identify logical sub-sections and extract them.
- **Single Responsibility**: Each component should do one thing well (e.g., display a status card, handle a form, render a list item).
- **Separation of Concerns**: Keep business logic (API calls, complex state management) separate from presentation logic whenever possible.

### 2. Reusability
- **Props-Driven**: Design components to be configured via props rather than relying on global state (where practical).
- **Agnostic Styling**: Avoid hardcoding layout-specific margins or widths inside reusable components. Use classes or container-level styles for positioning.
- **Slots for Flexibility**: Use Vue slots (or React children) to allow parent components to inject custom content.

### 3. Clean Patterns
- **Directory Structure**: Store reusable components in `src/components/`. Views/Pages should live in `src/views/`.
- **Naming Conventions**: Use PascalCase for component filenames and template tags (e.g., `TrialStatusCard.vue`, `<TrialStatusCard />`).
- **Composition API (Vue)**: Prefer the `<script setup>` syntax for better readability and performance.

## Refactoring Workflow

When encountering a "huge" component (like a multi-section dashboard):

1. **Identify Boundaries**: Look for visual or logical "blocks" (e.g., a notification panel, a data table, a status header).
2. **Extract with Props**: Create a new component file. Move the relevant HTML and CSS. Define props for any dynamic data.
3. **Replace and Verify**: Import the new component into the parent, pass the required props, and ensure functionality remains identical.
4. **Cleanup**: Remove the extracted CSS and logic from the parent component.

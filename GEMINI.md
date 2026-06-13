# Repository Instructions

This document outlines core mandates and workflows for the Phoenix Booking project.

## Skill Activation Mandates

- **Component Architect**: Always activate the `component-architect` skill when working on frontend UI tasks, implementing new components, or refactoring existing ones. This ensures consistency with the established design patterns and tech stack (Vue.js, PrimeVue, TailwindCSS).
- **Docker Executor**: Use the `docker-executor` skill when running commands that interact with the application environment (e.g., Symfony console commands, migrations, or npm scripts inside the container).

## Tech Stack Alignment

Always adhere to the tech stack context defined in the `component-architect` skill, prioritizing PrimeVue components and Tailwind utility classes for all frontend work.

## Context-Aware Documentation

- **Look up Context Directory**: Always check the [context/](file:///home/codingcube/dev/booking-app/context) directory for feature-specific workflows (e.g., [stripe_workflow.md](file:///home/codingcube/dev/booking-app/context/stripe_workflow.md) for Stripe-related tasks) before starting a task.
- **Maintain Context Sync**: If logic or configurations change, immediately update the corresponding files in the [context/](file:///home/codingcube/dev/booking-app/context) directory to maintain an accurate source of truth.

## Internationalization (i18n)

- **Use i18n for Text**: Whenever you create or modify user-facing text, labels, messages, or emails, always use internationalization (i18n). Do not hardcode strings in templates or source code.
- **Backend Translation**: Define all text keys in both `apps/backend/translations/messages.en.yaml` and `apps/backend/translations/messages.de.yaml` and translate using Symfony's translation mechanisms (e.g. `$translator->trans(...)` or `{{ 'key'|trans }}`).


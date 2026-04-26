---
name: latest-primevue
description: Guidance for using only the latest and non-deprecated PrimeVue components. Use when working on frontend tasks to ensure modern component usage.
---

# Latest PrimeVue Components

Enforce the usage of the most recent PrimeVue components, avoiding deprecated ones.

## Component Replacements

Always use the new component names as per PrimeVue 4+ standards:

- **ToggleSwitch** instead of `InputSwitch`
- **Select** instead of `Dropdown`
- **DatePicker** instead of `Calendar`
- **ToggleSwitch** for any boolean toggles.

## Import Patterns

Prefer importing from specific component paths for better tree-shaking, or use the centralized `primevue` package for common utilities:

```typescript
// Preferred for specific components
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import Select from 'primevue/select';

// Common utilities/smaller components
import { Checkbox, Divider, Password, Tag, Tooltip } from "primevue";
```

## CSS Classes

When styling or using `:deep()`, ensure you use the modern class prefixes:
- `.p-toggleswitch` (not `.p-inputswitch`)
- `.p-select` (not `.p-dropdown`)
- `.p-datepicker` (not `.p-calendar`)

# BooklyFit Landing Page

This is the static, SEO-optimized B2B/B2C landing page for **BooklyFit** (SaaS fitness course booking and attendance-tracking application). It is built using **Nuxt.js (Nuxt 3/4)**, **Tailwind CSS v4**, and **PrimeVue**.

---

## 🚀 Quick Start (Development)

To launch the local dev server for the landing page project via Docker (to avoid local host permission issues), run:

```bash
# Start the landing page service (runs on http://localhost:4300)
docker compose up -d landing
```

---

## 🛠️ Building & Previewing

To build the static application assets for production or type-check the source code:

```bash
# Build the production package
npx nx build landing

# Preview the production build locally
npx nx preview landing
```

---

## 📸 Automated Screenshot Generation

The landing page features high-DPI screenshots of both the athlete journey (Schedule, Booking Modal, Leaderboard) and the business panel (Payments, User Management, Settings).

Whenever the UI changes, you can re-capture all screenshots automatically.

### 1. Prerequisites
Make sure Playwright browsers and dependencies are installed (these can be run on the host or inside the node container):

```bash
# Install Playwright browser binaries
npx playwright install chromium

# Install missing system library dependencies (Linux host only)
npx playwright install-deps
```

### 2. Execution
Run the update command from the root directory. Ensure your frontend application dev server is running first.

```bash
# Capture screenshots using the default dev URL (http://localhost:5173)
npm run update-screenshots

# If your local dev server is running inside Docker (typically http://localhost:4200)
FRONTEND_URL=http://localhost:4200 npm run update-screenshots
```

Output screenshots are automatically placed in `apps/landing/public/screenshots/` and loaded dynamically by the page.

---

## 📂 Project Structure

*   `app/app.vue` - Global page layouts, theme wrapping, and root configurations.
*   `app/pages/index.vue` - Main landing page sections (Hero, Features, How It Works, CTA, Footer). Contains SEO metadata rules.
*   `app/assets/css/styles.css` - Stylesheet compiling Tailwind v4 and local custom CSS variables.
*   `nuxt.config.ts` - Main Nuxt configuration file. Loads Tailwind CSS v4, PrimeVue Nuxt module, and registers system paths.
*   `public/screenshots/` - Holds the auto-generated high-res screenshots.

---

## 🔧 Debugging & Common Tasks

### Customizing Meta / SEO Data
SEO metadata (Title, Description, Social Image) is defined at the top of the `app/pages/index.vue` component. Open it and modify the `useSeoMeta` block:

```typescript
useSeoMeta({
  title: 'BooklyFit - Manage Your Fitness Community',
  description: 'The modern SaaS platform for boutique gyms...',
  // ...
})
```

### Theme & PrimeVue Config
PrimeVue is configured to use the **Aura** preset. You can tweak preset prefixes, dark mode options, or custom overrides in `apps/landing/nuxt.config.ts` under the `primevue` block.

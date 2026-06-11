const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// Configuration with sensible defaults, overrideable via environment variables
const FRONTEND_URL = process.env.FRONTEND_URL || 'http://localhost:5173';

const MEMBER_EMAIL = process.env.SCREENSHOT_MEMBER_EMAIL || 'alice@phoenix.test';
const MEMBER_PASSWORD = process.env.SCREENSHOT_MEMBER_PASSWORD || 'test_123memberalice';

const ADMIN_EMAIL = process.env.SCREENSHOT_ADMIN_EMAIL || 'admin@phoenix.test';
const ADMIN_PASSWORD = process.env.SCREENSHOT_ADMIN_PASSWORD || 'test_123adminuser';

const OUTPUT_DIR = path.join(__dirname, '../../apps/landing/public/screenshots');

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Intercepts and rewrites localhost API/Mercure calls to container hostnames when running in Docker
async function setupContainerRouting(page) {
  // Capture console logs for debugging
  page.on('console', msg => console.log(`[Browser Console] ${msg.type().toUpperCase()}: ${msg.text()}`));
  page.on('pageerror', err => console.error(`[Browser JS Error]: ${err.toString()}`));

  await page.route('**', async (route) => {
    const url = route.request().url();
    if (url.includes('localhost:8000')) {
      const newUrl = url.replace('http://localhost:8000', 'http://backend');
      await route.continue({ url: newUrl });
    } else if (url.includes('localhost:3000')) {
      const newUrl = url.replace('http://localhost:3000', 'http://mercure');
      await route.continue({ url: newUrl });
    } else {
      await route.continue();
    }
  });
}

// Injects localStorage settings to disable push notification prompt modals and cookie consent banners
async function disableModals(page) {
  await page.evaluate(() => {
    // Dismiss push notification prompts for users 1 to 20
    for (let i = 1; i <= 20; i++) {
      localStorage.setItem(`push_prompted_user_${i}`, 'true');
    }
    // Dismiss cookie consent choice banner with full acceptance settings
    localStorage.setItem('cookie-consent-choice', JSON.stringify({
      essential: true,
      functional: true,
      analytical: true,
      marketing: true
    }));
  });
}

// Custom client-side navigation using the Vue Router to prevent token loss during page reload
async function navigateClientSide(page, targetPath) {
  const success = await page.evaluate((p) => {
    const appEl = document.querySelector('#root') || document.querySelector('#app');
    if (appEl && appEl.__vue_app__) {
      const provides = appEl.__vue_app__._context.provides;
      // Search for the router Symbol key within the provides object
      const routerKey = Object.getOwnPropertySymbols(provides).find(
        sym => sym.toString().includes('router')
      );
      const router = routerKey ? provides[routerKey] : provides.router;
      if (router) {
        router.push(p);
        return true;
      }
    }
    return false;
  }, targetPath);

  if (success) {
    console.log(`[Navigation] Navigated client-side to: ${targetPath}`);
  } else {
    console.log(`[Navigation] Vue Router not found. Falling back to page.goto for: ${targetPath}`);
    await page.goto(`${FRONTEND_URL}${targetPath}`);
  }
}

// Performs a complete login flow while ensuring modals are suppressed before input
async function loginAndDismissModals(page, email, password) {
  await page.goto(`${FRONTEND_URL}/login`);
  // Inject mock flags to localStorage
  await disableModals(page);
  // Reload the page to ensure mock settings are applied at component creation/mount
  await page.reload();

  await page.fill('input#email', email);
  await page.fill('input#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(url => url.pathname === '/' || url.pathname === '/dashboard', { timeout: 15000 });
}

// Navigates the calendar view next/prev until the week of June 15 - June 21, 2026 is visible
async function navigateToWeekOfJune15(page) {
  await page.waitForSelector('.pi-chevron-right', { timeout: 15000 });

  for (let i = 0; i < 12; i++) {
    const label = await page.locator('h2').first().textContent();
    console.log(`[Navigation] Target week check - Current label: "${label}"`);

    // Target checks (handles multiple language/date layouts)
    if (label.includes('15.06') || label.includes('15/06') || label.includes('15. Jun')) {
      console.log('[Navigation] Successfully aligned on week of June 15th to 21st.');
      break;
    }

    // Direct navigation: current local test date is June 11, 2026.
    // If the label date indicates a week past the target (e.g. 22.06 or July), click left, else right.
    const isAfterTarget = label.includes('22.06') || label.includes('29.06') || label.includes('July') || label.includes('07/') || label.includes('22/06');
    if (isAfterTarget) {
      await page.click('.pi-chevron-left');
    } else {
      await page.click('.pi-chevron-right');
    }
    await page.waitForTimeout(600); // let UI transitions finish
  }
}

// Dismisses active alerts/toasts, cookie buttons, and zooms the viewport for readability
async function cleanScreenshotPage(page, zoomFactor = '1.15') {
  // 1. Click "Einverstanden" or "Accept" if present
  try {
    const consentButton = page.locator('button:has-text("Einverstanden"), button:has-text("Accept"), button:has-text("Decline")').first();
    if (await consentButton.isVisible()) {
      console.log('[Clean View] Consent banner button visible, dismissing...');
      await consentButton.click();
      await page.waitForTimeout(500);
    }
  } catch (e) {}

  // 2. Clear any lingering toasts, onboarding widgets, or cookie modal elements from the DOM so they don't pollute screenshots
  await page.evaluate(() => {
    // Remove Toast popups
    const toasts = document.querySelectorAll('.p-toast, .p-toast-message, .p-toast-container');
    toasts.forEach(t => t.remove());

    // Remove Cookie banners
    const banners = document.querySelectorAll('.cookie-banner-wrapper, #cookie-consent-choice');
    banners.forEach(b => b.remove());

    // Remove Onboarding Widget / Tour components
    const onboarding = document.querySelectorAll('.floating-onboarding-container');
    onboarding.forEach(o => o.remove());
  });

  // 3. Zoom in slightly to make text readable in mocks
  if (zoomFactor) {
    await page.evaluate((z) => {
      document.body.style.zoom = z;
    }, zoomFactor);
    console.log(`[Clean View] Applied body zoom: ${zoomFactor}`);
  }
}

async function captureScreenshots() {
  console.log(`Starting screenshot pipeline...`);
  console.log(`Target Frontend URL: ${FRONTEND_URL}`);
  console.log(`Saving screenshots to: ${OUTPUT_DIR}`);

  const browser = await chromium.launch({ headless: true });

  try {
    // ==========================================
    // 1. MEMBER FLOW (Schedule, Booking Modal, Leaderboard)
    // ==========================================
    console.log('\n[Member Flow] Initializing context...');
    const memberContext = await browser.newContext({
      viewport: { width: 1440, height: 1000 },
      deviceScaleFactor: 2, // Retina quality
    });
    const memberPage = await memberContext.newPage();
    await setupContainerRouting(memberPage);

    console.log('[Member Flow] Logging in...');
    await loginAndDismissModals(memberPage, MEMBER_EMAIL, MEMBER_PASSWORD);
    console.log('[Member Flow] Successfully logged in.');

    // Navigate to target week first
    await navigateToWeekOfJune15(memberPage);

    // Screenshot 1: Schedule overview
    console.log('[Member Flow] Capturing Schedule Overview...');
    await cleanScreenshotPage(memberPage, '1.15');
    const schedulePath = path.join(OUTPUT_DIR, 'schedule.png');
    await memberPage.screenshot({ path: schedulePath, fullPage: false });
    console.log(`[Member Flow] Saved: ${schedulePath}`);

    // Reset zoom before modal opening to prevent positioning quirks, then zoom inside modal
    await memberPage.evaluate(() => { document.body.style.zoom = '1.0'; });

    // Screenshot 2: Booking modal/screen
    console.log('[Member Flow] Opening Booking Modal...');
    // Click on the first course card to open details modal
    const firstCourseCard = memberPage.locator('.course-card').first();
    await firstCourseCard.click();
    await memberPage.waitForSelector('.action-footer', { timeout: 10000 });
    await memberPage.waitForTimeout(1000); // Wait for modal slide-in animation to finish
    await cleanScreenshotPage(memberPage, '1.15');
    const bookingPath = path.join(OUTPUT_DIR, 'booking.png');
    await memberPage.screenshot({ path: bookingPath, fullPage: false });
    console.log(`[Member Flow] Saved: ${bookingPath}`);

    // Close dialog to proceed
    await memberPage.keyboard.press('Escape');
    await memberPage.waitForTimeout(500);

    // Screenshot 3: Attendance Leaderboard
    console.log('[Member Flow] Navigating to Attendance Leaderboard...');
    await navigateClientSide(memberPage, '/leaderboard');
    await memberPage.waitForSelector('h1', { timeout: 15000 });
    await memberPage.waitForTimeout(3000); // Wait for API response and render
    await cleanScreenshotPage(memberPage, '1.15');
    const leaderboardPath = path.join(OUTPUT_DIR, 'leaderboard.png');
    await memberPage.screenshot({ path: leaderboardPath, fullPage: false });
    console.log(`[Member Flow] Saved: ${leaderboardPath}`);

    await memberContext.close();

    // ==========================================
    // 2. MOBILE FLOW (Mobile Schedule View - Brighter Light Theme)
    // ==========================================
    console.log('\n[Mobile Flow] Initializing context (light mode/brighter view)...');
    const mobileContext = await browser.newContext({
      viewport: { width: 390, height: 844 }, // iPhone 12/13/14 Pro size
      deviceScaleFactor: 3,
      isMobile: true,
      hasTouch: true,
      colorScheme: 'light', // Force light theme / brighter view
      userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1'
    });
    const mobilePage = await mobileContext.newPage();
    await setupContainerRouting(mobilePage);

    console.log('[Mobile Flow] Logging in...');
    await loginAndDismissModals(mobilePage, MEMBER_EMAIL, MEMBER_PASSWORD);
    console.log('[Mobile Flow] Successfully logged in.');

    // Navigate to target week
    await navigateToWeekOfJune15(mobilePage);

    console.log('[Mobile Flow] Applying mobile light theme style injection...');
    await mobilePage.addStyleTag({ content: `
      .mobile-calendar .mobile-nav {
        background: #ffffff !important;
        color: #0f172a !important;
        border-bottom: 1px solid #e2e8f0 !important;
      }
      .mobile-calendar .mobile-nav h2 {
        color: #0f172a !important;
      }
      .mobile-calendar .mobile-nav button, .mobile-calendar .mobile-nav .p-button {
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
      }
      .mobile-cycle-info {
        background: #f1f5f9 !important;
        border-bottom: 1px solid #e2e8f0 !important;
      }
      .mobile-cycle-info span {
        color: #0f172a !important;
      }
      .mobile-cycle-info i {
        color: #475569 !important;
      }
      nav.mobile-nav {
        background: #ffffff !important;
        border-top: 1px solid #cbd5e1 !important;
        box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05) !important;
      }
      nav.mobile-nav .mobile-nav-item {
        color: #475569 !important;
      }
      nav.mobile-nav .mobile-nav-item.router-link-active {
        color: var(--primary-color, #ffc107) !important;
      }
    `});

    // Directly override the main header background and color using JavaScript to bypass scoped CSS variables
    await mobilePage.evaluate(() => {
      const header = document.querySelector('.main-header');
      if (header) {
        header.style.setProperty('background-color', '#ffffff', 'important');
        header.style.setProperty('background', '#ffffff', 'important');
        header.style.setProperty('border-bottom', '2px solid #e2e8f0', 'important');
        header.style.setProperty('backdrop-filter', 'none', 'important');
        header.style.setProperty('box-shadow', '0 2px 10px rgba(0, 0, 0, 0.05)', 'important');
        
        const children = header.querySelectorAll('*');
        children.forEach(el => {
          el.style.setProperty('color', '#0f172a', 'important');
          el.style.setProperty('border-color', '#cbd5e1', 'important');
          if (el.classList.contains('p-select') || el.classList.contains('p-dropdown') || el.classList.contains('p-button')) {
            el.style.setProperty('background', '#f8fafc', 'important');
            el.style.setProperty('background-color', '#f8fafc', 'important');
          }
        });
      }
    });

    console.log('[Mobile Flow] Capturing Mobile Schedule Overview...');
    await cleanScreenshotPage(mobilePage, null); // Keep mobile scale standard, just clear overlays
    const mobileSchedulePath = path.join(OUTPUT_DIR, 'mobile_schedule.png');
    await mobilePage.screenshot({ path: mobileSchedulePath, fullPage: false });
    console.log(`[Mobile Flow] Saved: ${mobileSchedulePath}`);

    await mobileContext.close();

    // ==========================================
    // 3. ADMIN FLOW (Payments, Users, Settings)
    // ==========================================
    console.log('\n[Admin Flow] Initializing context...');
    const adminContext = await browser.newContext({
      viewport: { width: 1440, height: 1000 },
      deviceScaleFactor: 2,
    });
    const adminPage = await adminContext.newPage();
    await setupContainerRouting(adminPage);

    console.log('[Admin Flow] Logging in...');
    await loginAndDismissModals(adminPage, ADMIN_EMAIL, ADMIN_PASSWORD);
    console.log('[Admin Flow] Successfully logged in.');

    // Screenshot 4: Payments view
    console.log('[Admin Flow] Navigating to Payments...');
    await navigateClientSide(adminPage, '/payments');
    await adminPage.waitForSelector('h1', { timeout: 15000 });
    await adminPage.waitForTimeout(3000);
    await cleanScreenshotPage(adminPage, '1.15');
    const paymentsPath = path.join(OUTPUT_DIR, 'payments.png');
    await adminPage.screenshot({ path: paymentsPath, fullPage: false });
    console.log(`[Admin Flow] Saved: ${paymentsPath}`);

    // Screenshot 5: User Management view
    console.log('[Admin Flow] Navigating to Users...');
    await navigateClientSide(adminPage, '/users');
    await adminPage.waitForSelector('h1', { timeout: 15000 });
    await adminPage.waitForTimeout(3000);
    await cleanScreenshotPage(adminPage, '1.15');
    const usersPath = path.join(OUTPUT_DIR, 'users.png');
    await adminPage.screenshot({ path: usersPath, fullPage: false });
    console.log(`[Admin Flow] Saved: ${usersPath}`);

    // Screenshot 6: Settings view
    console.log('[Admin Flow] Navigating to Settings...');
    await navigateClientSide(adminPage, '/settings');
    await adminPage.waitForSelector('h1', { timeout: 15000 });
    await adminPage.waitForTimeout(3000);
    await cleanScreenshotPage(adminPage, '1.15');
    const settingsPath = path.join(OUTPUT_DIR, 'settings.png');
    await adminPage.screenshot({ path: settingsPath, fullPage: false });
    console.log(`[Admin Flow] Saved: ${settingsPath}`);

    await adminContext.close();
    console.log('\nAll screenshots captured successfully!');

  } catch (error) {
    console.error('An error occurred during screenshot generation:', error);
  } finally {
    await browser.close();
  }
}

captureScreenshots();

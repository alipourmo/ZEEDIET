// Layout check for the public pages at 375 px (phone) and 1280 px (desktop) (Definition of Done, spec section 19).
// Run by CI against `php artisan serve` on 127.0.0.1:8000. It fails on: a wrong direction or language, horizontal
// scrolling, an element that runs past the right edge, or the Vazirmatn font not loaded. Screenshots go to build/layout.
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const base = process.env.LAYOUT_BASE_URL ?? 'http://127.0.0.1:8000';
const pages = ['/login', '/my-account/login'];
const widths = [375, 1280];
const outDir = 'build/layout';
mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const failures = [];

for (const width of widths) {
  for (const path of pages) {
    const context = await browser.newContext({ viewport: { width, height: 800 }, locale: 'fa-IR' });
    const page = await context.newPage();
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    const label = `${width}px ${path}`;

    if (!response || response.status() !== 200) {
      failures.push(`${label}: HTTP ${response?.status()}`);
      await context.close();
      continue;
    }

    await page.evaluate(() => document.fonts.ready);

    const info = await page.evaluate(() => {
      const root = document.documentElement;
      const viewport = root.clientWidth;
      const overflowing = [...document.querySelectorAll('body *')]
        .filter((el) => {
          // The skip link is parked off-screen on purpose until it is focused (public/css/app.css). Not a layout fault.
          if (el.classList.contains('skip-link')) return false;
          const rect = el.getBoundingClientRect();
          return rect.width > 0 && (rect.right > viewport + 1 || rect.left < -1);
        })
        .slice(0, 5)
        .map((el) => `${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).split(' ')[0] : ''}`);
      const vazir = [...document.fonts].filter((face) => face.family.includes('Vazirmatn'));
      return {
        dir: root.dir,
        lang: root.lang,
        scrollWidth: root.scrollWidth,
        viewport,
        overflowing,
        vazirLoaded: vazir.some((face) => face.status === 'loaded'),
      };
    });

    if (info.dir !== 'rtl') failures.push(`${label}: dir is "${info.dir}", expected rtl`);
    if (info.lang !== 'fa') failures.push(`${label}: lang is "${info.lang}", expected fa`);
    if (info.scrollWidth > info.viewport) failures.push(`${label}: horizontal scroll (${info.scrollWidth} > ${info.viewport})`);
    if (info.overflowing.length > 0) failures.push(`${label}: past the edge: ${info.overflowing.join(', ')}`);
    if (!info.vazirLoaded) failures.push(`${label}: Vazirmatn font not loaded`);

    const name = `${width}-${path.replace(/^\//, '').replace(/\//g, '_') || 'home'}.png`;
    await page.screenshot({ path: `${outDir}/${name}`, fullPage: true });
    console.log(`ok ${label} (scroll ${info.scrollWidth}/${info.viewport})`);
    await context.close();
  }
}

await browser.close();

if (failures.length > 0) {
  for (const failure of failures) console.error(`::error title=Layout::${failure}`);
  console.error(failures.join('\n'));
  process.exit(1);
}
console.log('layout check passed');

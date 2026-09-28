import { chromium } from "@playwright/test";
const [,, out, path] = process.argv;
const browser = await chromium.launch();
const ctx = await browser.newContext({ storageState: "tests/e2e/.auth/org-admin.json", locale: "de-DE" });
const page = await ctx.newPage();
for (const w of [1600, 1100, 390]) {
    await page.setViewportSize({ width: w, height: 800 });
    await page.goto(`http://127.0.0.1:8010${path}`);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${out}/invoice-${w}.png`, clip: { x: 0, y: 0, width: w, height: w < 600 ? 700 : 420 } });
}
await page.setViewportSize({ width: 1100, height: 800 });
await page.goto(`http://127.0.0.1:8010${path}`);
await page.locator("[data-toolbar-more] summary").click();
await page.waitForTimeout(300);
await page.screenshot({ path: `${out}/invoice-1100-menu.png`, clip: { x: 0, y: 0, width: 1100, height: 520 } });
await browser.close();

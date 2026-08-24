/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : auth.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

// Der Login-Spec prüft den Weg selbst — ohne vorgefertigte Session.
test.use({ storageState: { cookies: [], origins: [] } });

test("Login → Moduswechsel (mode/new) → Dashboard erreichbar", async ({ page }) => {
    await page.goto("/login");
    await page.fill('input[name="username"]', "test@example.com");
    await page.fill('input[name="password"]', "password");
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.startsWith("/login"));

    // work_mode-Falle: frischer Login kann im Legacy-Modus landen —
    // explizit in den neuen Modus wechseln (CSRF via XSRF-TOKEN-Cookie).
    const xsrf = (await page.context().cookies()).find(
        (c) => c.name === "XSRF-TOKEN",
    )?.value;
    expect(xsrf).toBeTruthy();
    const mode = await page.request.post("/mode/new", {
        headers: { "X-XSRF-TOKEN": decodeURIComponent(String(xsrf)) },
    });
    expect(mode.ok()).toBeTruthy();

    await page.goto("/dashboard");
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator("main")).toBeVisible();
});

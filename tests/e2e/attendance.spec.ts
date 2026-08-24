/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";
import {
    CLOCK_IN,
    CLOCK_OUT,
    clockInSubmit,
    clockOutSubmit,
    ensureClockedOut,
} from "./helpers";

test("Stempeluhr auf /today: Einstempeln und Ausstempeln", async ({ page }) => {
    await page.goto("/today");
    await ensureClockedOut(page);

    // Einstempeln → normaler POST mit Redirect, danach läuft die Uhr.
    await clockInSubmit(page).click();
    await expect(clockOutSubmit(page)).toBeVisible();
    await expect(page.locator(CLOCK_IN)).toHaveCount(0);

    // Ausstempeln (Pause bleibt 0) → wieder einstempelbar.
    await clockOutSubmit(page).click();
    await expect(clockInSubmit(page)).toBeVisible();
    await expect(page.locator(CLOCK_OUT)).toHaveCount(0);
});

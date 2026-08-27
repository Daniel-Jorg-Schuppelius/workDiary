/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard-customize.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

/**
 * Symbolauswahl der Bereiche. Sie war zuerst eine <datalist> — die zeigt aber
 * nur Namen und filtert bei exaktem Treffer alles weg, das Feld wirkt dann
 * leer. Jetzt ein Raster echter Symbole; der Test hält fest, dass sie
 * gerendert werden und die Auswahl im Feld landet.
 */
test("Bereichs-Symbole lassen sich aus dem Raster wählen", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/me/dashboard/customize");

    // Bereiche entstehen über das klassische Preset.
    await page.locator('button[form="dashboard-preset-classic"]').click();
    await page.waitForLoadState("networkidle");

    const row = page.locator("[data-tab-row]").first();
    await row.locator("summary").click();

    const grid = row.locator("[data-icon-grid]");
    await expect(grid).toBeVisible();
    await expect(grid.locator("[data-icon-pick]")).not.toHaveCount(0);

    // Das Symbol muss als Glyphe gerendert sein, nicht als leerer Kasten.
    const box = await grid.locator("[data-icon-pick] .material-symbols-outlined").first().boundingBox();
    expect(box?.width ?? 0).toBeGreaterThan(10);

    await grid.locator('[data-icon-pick="handyman"]').click();
    await expect(row.locator("[data-tab-icon]")).toHaveValue("handyman");
    await expect(row.locator("[data-tab-icon-preview]")).toHaveText("handyman");
});

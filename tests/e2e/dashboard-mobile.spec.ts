/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard-mobile.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Dashboard auf schmalen Handys. Ohne feste Spalte (grid-cols-1) wuchs die
 * implizite Rasterspalte auf die Mindestbreite der breitesten Kachel (nicht
 * umbrechender Kartentitel) und alle Kacheln ragten rechts aus dem Bild.
 * Das ⋯-Menü der Kopfleiste stand hinter „Wochenansicht“ und klappte links
 * aus dem Bild.
 */

import { test, expect } from "@playwright/test";

const WIDTH = 360;

test(`Kacheln und ⋯-Menü bleiben bei ${WIDTH} px im Bild`, async ({ page }) => {
    await page.setViewportSize({ width: WIDTH, height: 780 });
    await page.goto("/dashboard");

    const grid = page.locator(".wd-page-shell .grid").filter({ visible: true }).first();
    const gridBox = await grid.boundingBox();
    expect(gridBox).not.toBeNull();
    const gridRight = (gridBox?.x ?? 0) + (gridBox?.width ?? 0);

    const tiles = grid.locator("xpath=./*").filter({ visible: true });
    expect(await tiles.count()).toBeGreaterThan(0);
    for (const tile of await tiles.all()) {
        const box = await tile.boundingBox();
        expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(gridRight + 1);
    }

    const more = page.locator("[data-toolbar] [data-toolbar-more]");
    await more.locator("summary").click();
    const menu = await more.locator(".wd-toolbar-menu").boundingBox();
    expect(menu?.x).toBeGreaterThanOrEqual(0);
    expect((menu?.x ?? 0) + (menu?.width ?? 0)).toBeLessThanOrEqual(WIDTH);
});

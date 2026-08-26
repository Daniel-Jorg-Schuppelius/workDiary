/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

// Die Zug-Gesten (Zeiger + Tastatur) stehen in kanban-dnd.spec.ts.

test("Kanban-Board rendert Spalten", async ({ page }) => {
    await page.goto("/kanban");
    await expect(page.locator("[data-kanban-board]")).toBeVisible();
    expect(await page.locator("[data-kanban-column]").count()).toBeGreaterThan(2);
});

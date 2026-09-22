/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dialog-drag.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Verschiebbare Dialoge (Pattern-Katalog §3.3): Ziehen am Header bewegt die
 * Box, Bedienelemente im Header starten keinen Drag, nach dem Schließen
 * öffnet der wiederverwendete #entry-modal-Host wieder zentriert.
 * Ohne page.evaluate — die CSP bleibt an; Geometrie über boundingBox().
 */

import { test, expect, type Page } from "@playwright/test";

const BOX = "dialog[open] > .modal-box";
const HEADER = `${BOX} .wd-dialog__header`;

async function openEntryDialog(page: Page) {
    await page
        .locator('a[data-entry-modal-trigger][href*="/diary/create"]')
        .filter({ visible: true })
        .first()
        .click();
    await expect(page.locator(`${BOX} form[data-entry-form]`)).toBeVisible();
    // daisyUI blendet die Box mit einer 0,3-s-Transition ein.
    await page.waitForTimeout(400);
}

/** Freie Header-Fläche: rechts vom Titel, links vor Badge/Close-Button. */
async function headerGrip(page: Page) {
    const header = page.locator(HEADER);
    const rect = await header.boundingBox();
    if (!rect) throw new Error("Dialog-Header nicht sichtbar.");
    const title = await page.locator(`${HEADER} h2`).boundingBox();
    const x = title ? title.x + title.width + 24 : rect.x + rect.width * 0.5;
    return { x: Math.min(x, rect.x + rect.width - 80), y: rect.y + rect.height / 2 };
}

test.describe("Dialog am Header verschieben", () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto("/dashboard");
        await openEntryDialog(page);
    });

    test("Ziehen am Header bewegt die Box, Schließen setzt zurück", async ({ page }) => {
        const box = page.locator(BOX);
        const before = await box.boundingBox();
        if (!before) throw new Error("Dialog-Box nicht sichtbar.");

        const grip = await headerGrip(page);
        await page.mouse.move(grip.x, grip.y);
        await page.mouse.down();
        await page.mouse.move(grip.x + 60, grip.y + 40, { steps: 5 });
        await page.mouse.move(grip.x + 120, grip.y + 80, { steps: 5 });
        await page.mouse.up();

        const after = await box.boundingBox();
        expect(after).not.toBeNull();
        expect(Math.round(after!.x - before.x)).toBe(120);
        expect(Math.round(after!.y - before.y)).toBe(80);

        // Schließen → derselbe Host öffnet wieder zentriert.
        await page.locator(`${HEADER} [data-entry-modal-close]`).click();
        await expect(page.locator("dialog[open]")).toHaveCount(0);
        await openEntryDialog(page);
        const reopened = await box.boundingBox();
        expect(Math.round(reopened!.x)).toBe(Math.round(before.x));
        expect(Math.round(reopened!.y)).toBe(Math.round(before.y));
    });

    test("Box bleibt im Sichtbereich: Header nicht über den oberen Rand", async ({ page }) => {
        const box = page.locator(BOX);
        const grip = await headerGrip(page);
        await page.mouse.move(grip.x, grip.y);
        await page.mouse.down();
        await page.mouse.move(grip.x, 5, { steps: 10 });
        await page.mouse.up();

        const after = await box.boundingBox();
        expect(after!.y).toBeGreaterThanOrEqual(0);
        expect(after!.y).toBeLessThan(2);
    });

    test("Schließen-Button im Header startet keinen Drag, sondern schließt", async ({ page }) => {
        const close = page.locator(`${HEADER} [data-entry-modal-close]`);
        const rect = await close.boundingBox();
        if (!rect) throw new Error("Schließen-Button nicht sichtbar.");
        const cx = rect.x + rect.width / 2;
        const cy = rect.y + rect.height / 2;

        await page.mouse.move(cx, cy);
        await page.mouse.down();
        await page.mouse.move(cx + 80, cy + 80, { steps: 5 });
        await page.mouse.up();

        // Kein Drag: Box unverändert; der Klick landet nicht auf dem Button
        // (Pointer beim Loslassen außerhalb), der Dialog bleibt offen …
        await expect(page.locator("dialog[open]")).toHaveCount(1);
        const box = await page.locator(BOX).boundingBox();
        expect(Math.round(box!.x)).toBe(Math.round((1280 - box!.width) / 2));

        // … und ein echter Klick schließt.
        await close.click();
        await expect(page.locator("dialog[open]")).toHaveCount(0);
    });
});

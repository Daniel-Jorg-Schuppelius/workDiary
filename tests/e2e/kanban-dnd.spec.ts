/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban-dnd.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Kanban-Züge über Pointer Events + Tastatur (MVP-725).
 *
 * Der frühere Spec war übersprungen, weil HTML5-Drag-and-drop mit
 * synthetischen Playwright-Events nicht stabil auszulösen ist. Seit dem Umbau
 * von kanban.js auf Pointer Events genügt mouse.down/move/up — Chromium
 * erzeugt daraus echte pointerdown/-move/-up-Ereignisse.
 *
 * Der Zug selbst läuft weiterhin über `POST diary/{diary}/lifecycle/{action}`
 * (nie ein direkter Status-Schreibzugriff), die Seite lädt danach neu.
 * Vorbedingungen (Rezept): Session + work_mode=new liefert global-setup.ts,
 * den enterprise-Plan gegen das Modul-Gate setzt tests/e2e/server.sh.
 */

import { test, expect, type Page, type Locator } from "@playwright/test";
import { STATUS } from "./helpers";

/**
 * Legt einen terminierten Auftrag von heute an (landet in „Geplant").
 *
 * Über das Formular `/diary/create?date=…`. Flatpickr versteckt `start_at` und
 * `end_at`; beschrieben wird das sichtbare Zweitfeld daneben (deutsches
 * Format, Tab übernimmt), wie in diary-entry-defaults.spec.ts.
 */
async function createOrderForToday(page: Page): Promise<string> {
    const marker = `E2E-Kanban-${Date.now()}`;
    const today = new Date();
    const pad = (value: number) => String(value).padStart(2, "0");
    const isoDay = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
    const deDay = `${pad(today.getDate())}.${pad(today.getMonth() + 1)}.${today.getFullYear()}`;

    await page.goto(`/diary/create?date=${isoDay}`);

    const form = page.locator("form[data-entry-form]");
    await expect(form.locator('textarea[name="content"]')).toBeVisible();
    // Terminiert (fester Zeitraum) — sonst fällt der Eintrag aus dem globalen
    // Zeitraum (Default „dieser Monat") und damit vom Board.
    await form.locator('select[name="mode"]').selectOption("fixed");
    await expect(form.locator('input[name="start_at"]')).toHaveValue(new RegExp(`^${isoDay}T`));
    // Start explizit auf Tagesmitte: der vorbelegte 00:00-Start liegt in UTC
    // (Europe/Berlin −2 h) im VORTAG — am Monatsersten fiel die Karte damit
    // aus dem „Dieser Monat"-Fenster (dokumentierte UTC-Schnittsemantik von
    // DateRange::whereTimestampBetween, kein App-Bug). Erst am 01.09. rot.
    await form.locator("#start_at + input").fill(`${deDay} 09:00`);
    await form.locator("#start_at + input").press("Tab");
    // „Terminiert" verlangt auch ein Ende.
    await form.locator("#end_at + input").fill(`${deDay} 17:00`);
    await form.locator("#end_at + input").press("Tab");
    await expect(form.locator('input[name="start_at"]')).toHaveValue(`${isoDay}T09:00`);
    await form
        .locator('textarea[name="content"]')
        .fill(`${marker} — Kanban-Zug per Pointer/Tastatur.`);

    await page.getByRole("button", { name: "Auftrag anlegen" }).click();
    await expect(page.locator("form[data-entry-form]")).toHaveCount(0, { timeout: 15_000 });

    return marker;
}

function card(page: Page, marker: string): Locator {
    return page.locator("[data-kanban-card]").filter({ hasText: marker });
}

function column(page: Page, status: number): Locator {
    return page.locator(`[data-kanban-column][data-status="${status}"]`);
}

/** Zieht die Karte mit echten Zeigerereignissen in die Zielspalte. */
async function dragCardTo(page: Page, source: Locator, target: Locator) {
    const from = await source.boundingBox();
    const to = await target.boundingBox();
    if (!from || !to) throw new Error("Karte oder Zielspalte ohne Bounding-Box.");

    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    // Mehrere Zwischenschritte: der erste überschreitet die Zug-Schwelle,
    // die folgenden setzen das Ziel-Highlight.
    await page.mouse.move(to.x + to.width / 2, to.y + 40, { steps: 12 });
    await page.mouse.up();
}

test("Kanban-Zug per Zeigergeste läuft über diary.lifecycle", async ({ page }) => {
    const marker = await createOrderForToday(page);

    await page.goto("/kanban");
    await expect(column(page, STATUS.PLANNED).locator("[data-kanban-card]").filter({ hasText: marker })).toHaveCount(1);

    await dragCardTo(page, card(page, marker), column(page, STATUS.ACCEPTED));

    // Der Zug ist ein Form-POST mit Redirect — die Seite lädt neu.
    await expect(
        column(page, STATUS.ACCEPTED).locator("[data-kanban-card]").filter({ hasText: marker }),
    ).toHaveCount(1, { timeout: 15_000 });
    await expect(
        column(page, STATUS.PLANNED).locator("[data-kanban-card]").filter({ hasText: marker }),
    ).toHaveCount(0);
});

test("Unzulässiger Zug wird abgewiesen statt geschrieben", async ({ page }) => {
    const marker = await createOrderForToday(page);

    await page.goto("/kanban");
    // Geplant → Berechnet gibt es im Auftragsworkflow nicht.
    await dragCardTo(page, card(page, marker), column(page, STATUS.INVOICED));

    await expect(
        column(page, STATUS.PLANNED).locator("[data-kanban-card]").filter({ hasText: marker }),
    ).toHaveCount(1);
    await expect(
        column(page, STATUS.INVOICED).locator("[data-kanban-card]").filter({ hasText: marker }),
    ).toHaveCount(0);
});

test("Tastaturweg: Verschieben-Menü bietet genau die erlaubten Spalten", async ({ page }) => {
    const marker = await createOrderForToday(page);

    await page.goto("/kanban");
    // Karte fokussieren (der Eintrags-Link ist das fokussierbare Element)
    // und das Menü über das Kürzel öffnen.
    await card(page, marker).locator("a[data-entry-modal-trigger]").focus();
    await page.keyboard.press("m");

    const dialog = page.locator("dialog#kanban-move-dialog[open]");
    await expect(dialog).toBeVisible();

    const options = dialog.locator("[data-kanban-move-options] button");
    // Geplant erlaubt genau „Angenommen" (accept) und „Storniert" (cancel).
    await expect(options).toHaveCount(2);
    await expect(
        dialog.locator(`[data-kanban-move-target="${STATUS.ACCEPTED}"]`),
    ).toBeVisible();

    await dialog.locator(`[data-kanban-move-target="${STATUS.ACCEPTED}"]`).click();

    await expect(
        column(page, STATUS.ACCEPTED).locator("[data-kanban-card]").filter({ hasText: marker }),
    ).toHaveCount(1, { timeout: 15_000 });
});

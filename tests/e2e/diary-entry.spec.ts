/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary-entry.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

test("Auftrag über den Modal-Dialog anlegen", async ({ page }) => {
    // Eindeutiger Marker im Pflichtfeld content — das Titelfeld ist
    // typgesteuert (nur mit gewähltem Eintragstyp sichtbar).
    const marker = `E2E-Auftrag-${Date.now()}`;

    await page.goto("/dashboard");
    // Gezielt der "Neuer Eintrag"-Trigger — es gibt weitere Modal-Trigger
    // (z. B. Lesezeichen) mit demselben data-Attribut.
    await page
        .locator('a[data-entry-modal-trigger][href*="/diary/create"]')
        .filter({ visible: true })
        .first()
        .click();

    // Das Dialog-Fragment lädt asynchron in <dialog open> nach.
    const form = page.locator("dialog[open] form[data-entry-form]");
    await expect(form.locator('textarea[name="content"]')).toBeVisible();

    // Backlog-Modus: keine Pflicht-Termine — der Spec prüft den Dialogweg,
    // nicht die Terminlogik.
    await form.locator('select[name="mode"]').selectOption("backlog");
    await form
        .locator('textarea[name="content"]')
        .fill(`${marker} — über den E2E-Modal-Dialog angelegt.`);
    // Der Submit-Knopf sitzt im Dialog-Footer AUSSERHALB des <form>
    // (form="..."-Attribut) — daher auf den Dialog scopen.
    await page
        .locator("dialog[open]")
        .getByRole("button", { name: "Auftrag anlegen" })
        .click();

    // Dialog-Submit läuft als fetch; bei Erfolg lädt die Seite neu bzw.
    // folgt dem Redirect — der offene Dialog verschwindet in beiden Fällen.
    await expect(page.locator("dialog[open]")).toHaveCount(0, {
        timeout: 15_000,
    });

    // Nachweis über die Auftragsliste (q-Filter).
    await page.goto(`/diary?q=${encodeURIComponent(marker)}`);
    await expect(page.getByText(marker).first()).toBeVisible();
});

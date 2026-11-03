/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary-entry-defaults.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Vorbelegungen im Auftragsdialog (Pilot 2026-11-03): Ein Typ, der Adresse
 * und Termin verlangt, bekommt den Einsatzort aus der Kundenanschrift und
 * das Einsatzdatum von heute bzw. vom Tag des Beginns — eigene Eingaben
 * überlebt der Kundenwechsel. Nur der Browser zeigt, ob die Alpine-Kopplung
 * (CSP-Build, x-if-Blöcke) wirklich greift.
 */

import { test, expect, type Locator } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const STAMP = Date.now();
const CUSTOMER = `E2E Adresskunde ${STAMP}`;
const CUSTOMER_PLAIN = `E2E Kunde ohne Anschrift ${STAMP}`;

function seedCustomers(): void {
    const code = `
        $org = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id');
        \\App\\Models\\Customer\\Customer::factory()->create(['organization_id' => $org, 'name' => '${CUSTOMER}', 'company' => null,
            'address_street' => 'Musterweg 7', 'address_zip' => '12345', 'address_city' => 'Musterstadt', 'country' => 'DE',
            'address_lat' => '52.5200000', 'address_lng' => '13.4050000']);
        \\App\\Models\\Customer\\Customer::factory()->create(['organization_id' => $org, 'name' => '${CUSTOMER_PLAIN}', 'company' => null,
            'address_street' => null, 'address_zip' => null, 'address_city' => null, 'address_lat' => null, 'address_lng' => null]);
        echo 'OK';
    `;
    const out = execFileSync(process.env.PHP_BIN || "php", ["artisan", "tinker", `--execute=${code}`], {
        cwd: ROOT,
        env: {
            ...process.env,
            DB_DATABASE: path.join(ROOT, "database", "testing.sqlite-ui"),
            CACHE_STORE: "array",
            APP_INSTALLED: "true",
        },
        encoding: "utf8",
    });
    if (!out.includes("OK")) throw new Error(`Kunden nicht angelegt:\n${out}`);
}

/** Optionen tragen „Name — Firma“; der Wert ist die Sqid, daher über den Text suchen. */
async function selectCustomer(form: Locator, name: string) {
    const value = await form.locator('select[name="customer_id"] option', { hasText: name }).first().getAttribute("value");
    if (!value) throw new Error(`Kunde „${name}“ fehlt in der Auswahl.`);
    await form.locator('select[name="customer_id"]').selectOption(value);
}

test.beforeAll(() => {
    seedCustomers();
});

test("Einsatzort aus der Kundenanschrift, Einsatzdatum von heute bzw. vom Beginn", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto("/dashboard");
    await page
        .locator('a[data-entry-modal-trigger][href*="/diary/create"]')
        .filter({ visible: true })
        .first()
        .click();

    const form = page.locator("dialog[open] form[data-entry-form]");
    await expect(form.locator('textarea[name="content"]')).toBeVisible();

    // Der Service-Auftrag verlangt Kunde, Adresse und Termin (EntryTypeSeeder).
    await form.locator('select[name="entry_type_id"]').selectOption({ label: "Service-Auftrag" });
    const today = new Date().toLocaleDateString("sv-SE", { timeZone: "Europe/Berlin" });
    await expect(form.locator('input[name="scheduled_for"]')).toHaveValue(today);
    await expect(form.locator('input[name="address_line"]')).toHaveValue("");

    await selectCustomer(form, CUSTOMER);
    await expect(form.locator('input[name="address_line"]')).toHaveValue("Musterweg 7");
    await expect(form.locator('input[name="address_zip"]')).toHaveValue("12345");
    await expect(form.locator('input[name="address_city"]')).toHaveValue("Musterstadt");
    await expect(form.locator('input[name="address_country"]')).toHaveValue("DE");
    await expect(form.locator('input[name="address_lat"]')).toHaveValue("52.5200000");
    await expect(form.locator('input[name="address_lng"]')).toHaveValue("13.4050000");
    await expect(form.locator('[data-geocode-status="address"]')).toContainText("Anschrift des Kunden übernommen");

    // Fester Zeitraum: das Einsatzdatum folgt dem Tag des Beginns. Flatpickr
    // versteckt die eigentlichen Felder; bedient wird das sichtbare Zweitfeld
    // daneben (deutsches Format, allowInput), Tab übernimmt die Eingabe.
    await form.locator('select[name="mode"]').selectOption("fixed");
    await form.locator("#start_at + input").fill("16.03.2030 09:00");
    await form.locator("#start_at + input").press("Tab");
    await form.locator("#end_at + input").fill("16.03.2030 10:00");
    await form.locator("#end_at + input").press("Tab");
    await expect(form.locator('input[name="start_at"]')).toHaveValue("2030-03-16T09:00");
    await expect(form.locator('input[name="scheduled_for"]')).toHaveValue("2030-03-16");

    // Übernommene Anschrift folgt dem Kundenwechsel: ohne Anschrift wird sie geleert.
    await selectCustomer(form, CUSTOMER_PLAIN);
    await expect(form.locator('input[name="address_line"]')).toHaveValue("");
    await expect(form.locator('input[name="address_lat"]')).toHaveValue("");

    // Eigene Eingabe überlebt den Kundenwechsel.
    await form.locator('input[name="address_line"]').fill("Baustelle 3");
    await selectCustomer(form, CUSTOMER);
    await expect(form.locator('input[name="address_line"]')).toHaveValue("Baustelle 3");
    await expect(form.locator('input[name="address_city"]')).toHaveValue("");
});

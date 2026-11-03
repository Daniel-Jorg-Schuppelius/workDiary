/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : site-prefill.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Vorbelegung aus der Elternauswahl im Standort-Dialog (Pilot 2026-11-03):
 * Die Kundenwahl belegt Anschrift, Land und Koordinaten, eigene Eingaben
 * überleben den Wechsel, die Abwahl leert nur Übernommenes. Prüft den
 * generischen Binder `data-prefill-from` in app.js im Browser.
 */

import { test, expect, type Locator } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const STAMP = Date.now();
const CUSTOMER = `E2E Standortkunde ${STAMP}`;
const CUSTOMER_PLAIN = `E2E Standortkunde ohne Anschrift ${STAMP}`;

function seedCustomers(): void {
    const code = `
        $org = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id');
        \\App\\Models\\Customer\\Customer::factory()->create(['organization_id' => $org, 'name' => '${CUSTOMER}', 'company' => null,
            'address_street' => 'Musterweg 7', 'address_zip' => '12345', 'address_city' => 'Musterstadt', 'country' => 'DE',
            'address_lat' => '52.5200000', 'address_lng' => '13.4050000']);
        \\App\\Models\\Customer\\Customer::factory()->create(['organization_id' => $org, 'name' => '${CUSTOMER_PLAIN}', 'company' => null,
            'address_street' => null, 'address_zip' => null, 'address_city' => null, 'country' => null, 'address_lat' => null, 'address_lng' => null]);
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

async function selectCustomer(form: Locator, name: string) {
    const value = await form.locator('select[name="customer_id"] option', { hasText: name }).first().getAttribute("value");
    if (!value) throw new Error(`Kunde „${name}“ fehlt in der Auswahl.`);
    await form.locator('select[name="customer_id"]').selectOption(value);
}

test.beforeAll(() => {
    seedCustomers();
});

test("Standort-Dialog übernimmt die Kundenanschrift, eigene Eingaben bleiben", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto("/sites");
    await page.locator('a[data-entry-modal-trigger][href$="/sites/create"]').filter({ visible: true }).first().click();

    const form = page.locator("dialog[open] form[data-entry-form]");
    await expect(form.locator('input[name="name"]')).toBeVisible();

    await selectCustomer(form, CUSTOMER);
    await expect(form.locator('input[name="address_street"]')).toHaveValue("Musterweg 7");
    await expect(form.locator('input[name="address_zip"]')).toHaveValue("12345");
    await expect(form.locator('input[name="address_city"]')).toHaveValue("Musterstadt");
    await expect(form.locator('input[name="country"]')).toHaveValue("DE");
    await expect(form.locator('input[name="geo_lat"]')).toHaveValue("52.5200000");
    await expect(form.locator('input[name="geo_lng"]')).toHaveValue("13.4050000");

    // Abwahl leert nur Übernommenes.
    await selectCustomer(form, CUSTOMER_PLAIN);
    await expect(form.locator('input[name="address_street"]')).toHaveValue("");
    await expect(form.locator('input[name="geo_lat"]')).toHaveValue("");

    // Eigene Eingabe überlebt den Kundenwechsel; die Wächter sperren auch die Folgefelder.
    await form.locator('input[name="address_street"]').fill("Baustelle 3");
    await selectCustomer(form, CUSTOMER);
    await expect(form.locator('input[name="address_street"]')).toHaveValue("Baustelle 3");
    await expect(form.locator('input[name="address_city"]')).toHaveValue("");
    await expect(form.locator('input[name="geo_lat"]')).toHaveValue("");
});

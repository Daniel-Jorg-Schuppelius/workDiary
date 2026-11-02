/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Reselling im Browser (Vollscan 2026-11-02, gap-11 / lk-38): die
 * Pilotbefunde der Phasen 163–168 waren Dialog- und Layoutfehler, die kein
 * HTTP-Test sieht. Drei Wege: Mappe → Schnellzuordnung → Rechnungsentwurf
 * (lokal, Fakturierungsweg WorkDiary), Abo-Import bis zur Vorschau (und
 * Verwerfen), Zählkacheln der Mappe filtern die Tafel.
 *
 * Die Daten entstehen direkt (gespiegelte Lexoffice-Belege ohne API, wie in
 * den Feature-Tests): ein Exchange-Abo mit drei Jahresperioden, dessen erste
 * Periode eine passende Rechnung hat, und eine Standard-Rechnung ohne Abo.
 */

import { test, expect, type Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const CUSTOMER = "Kachelberg GmbH";
const EXCHANGE = "Exchange Online (Plan 1)";
const STANDARD = "Microsoft 365 Business Standard";

/** Rechnungsnummern erfunden (RE/jjjj/9001 ff.), Daten relativ zu heute. */
function seedResale(): { workspace: string; invoice: string } {
    const code = `
        $org = \\App\\Models\\Platform\\Organization::query()->findOrFail(\\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id'));
        $today = \\App\\Models\\Reselling\\ResalePeriod::today();
        \\App\\Models\\Platform\\PluginSetting::query()->updateOrCreate(['organization_id' => $org->id, 'plugin_id' => 'lexoffice'], ['enabled' => true, 'settings' => []]);
        foreach (\\App\\Models\\Customer\\Customer::withoutGlobalScopes()->where('organization_id', $org->id)->where('name', '${CUSTOMER}')->get() as $old) {
            \\App\\Models\\Reselling\\ResaleSubscription::withoutGlobalScopes()->where('customer_id', $old->id)->get()->each(fn ($s) => $s->periods()->get()->each(fn ($p) => $p->links()->delete()) && $s->periods()->delete() && $s->delete());
            \\App\\Models\\Invoicing\\Invoice::withoutGlobalScopes()->where('customer_id', $old->id)->get()->each(fn ($i) => $i->items()->delete() && $i->delete());
            \\App\\Models\\Integration\\ExternalReference::withoutGlobalScopes()->where('referenceable_type', $old->getMorphClass())->where('referenceable_id', $old->id)->delete();
            $old->forceDelete();
        }
        \\App\\Plugins\\Lexoffice\\Models\\LexofficeVoucherLine::withoutGlobalScopes()->whereIn('voucher_id', \\App\\Plugins\\Lexoffice\\Models\\LexofficeVoucher::withoutGlobalScopes()->where('contact_external_id', 'c-e2e')->select('id'))->delete();
        \\App\\Plugins\\Lexoffice\\Models\\LexofficeVoucher::withoutGlobalScopes()->where('contact_external_id', 'c-e2e')->delete();
        $article = fn (string $id, string $name, string $price) => \\App\\Plugins\\Lexoffice\\Models\\LexofficeArticle::withoutGlobalScopes()->updateOrCreate(['organization_id' => $org->id, 'external_id' => $id], [
            'name' => $name, 'article_number' => strtoupper($id), 'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => $price, 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $exchange = $article('art-e2e-exo', '${EXCHANGE}', '3.95');
        $standard = $article('art-e2e-m365', '${STANDARD}', '11.70');
        $customer = \\App\\Models\\Customer\\Customer::factory()->create(['organization_id' => $org->id, 'name' => '${CUSTOMER}', 'billing_mode' => \\App\\Enums\\Finance\\BillingMode::Workdiary]);
        \\App\\Models\\Integration\\ExternalReference::create(['organization_id' => $org->id, 'plugin_id' => 'lexoffice', 'external_type' => 'contact', 'external_id' => 'c-e2e', 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey()]);
        $starts = $today->subYears(2)->subDays(10);
        $subscription = \\App\\Models\\Reselling\\ResaleSubscription::query()->create([
            'organization_id' => $org->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'provider_account' => 'acc-e2e', 'label' => '${EXCHANGE}', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $customer->id, 'article_ref' => 'lex:' . $exchange->id, 'starts_on' => $starts->toDateString(),
        ]);
        (new \\App\\Services\\Reselling\\Register\\PeriodPlanner)->sync($subscription);
        $invoice = function (\\App\\Plugins\\Lexoffice\\Models\\LexofficeArticle $art, string $number, \\Carbon\\CarbonImmutable $date, ?\\Carbon\\CarbonImmutable $to) use ($org) {
            $voucher = \\App\\Plugins\\Lexoffice\\Models\\LexofficeVoucher::create([
                'organization_id' => $org->id, 'external_id' => 'v-e2e-' . $number, 'contact_external_id' => 'c-e2e', 'voucher_type' => 'invoice', 'voucher_status' => 'paid',
                'voucher_number' => $number, 'voucher_date' => $date->toDateString(), 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
                'service_starts_on' => $to === null ? null : $date->toDateString(), 'service_ends_on' => $to?->toDateString(), 'lines_synced_at' => now(),
            ]);
            \\App\\Plugins\\Lexoffice\\Models\\LexofficeVoucherLine::create([
                'organization_id' => $org->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service', 'external_article_id' => $art->external_id,
                'lexoffice_article_id' => $art->id, 'name' => $art->name, 'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        };
        $number = 'RE/' . $starts->year . '/9001';
        $invoice($exchange, $number, $starts, $starts->addYear()->subDay());
        $invoice($standard, 'RE/' . $today->year . '/9002', $today->subDays(40), null);
        echo 'OK ' . route('finance.resale.reconcile.show', $customer) . ' ' . $number;
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
    const match = out.match(/OK (\S+) (\S+)/);
    if (!match) throw new Error(`Reselling-Daten nicht angelegt:\n${out}`);
    const workspace = new URL(match[1]).pathname;
    return { workspace, invoice: match[2] };
}

let seeded: { workspace: string; invoice: string };

test.beforeAll(() => {
    seeded = seedResale();
});

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
});

/** Zahl einer Zählkachel über ihren Beschriftungstext (die Kachel ist ein Link). */
function tile(page: Page, label: string) {
    return page.getByRole("main").getByRole("link", { name: new RegExp(`^${label}\\b`) }).first();
}

test("Mappe: Schnellzuordnung von der Tafel, Kacheln filtern, Rechnungsentwurf lokal", async ({ page }) => {
    // Einstieg über den Abgleich je Rechnungsempfänger, nicht per Direkt-URL.
    await page.goto("/finanzen/abos/abgleich");
    await page.getByRole("main").getByRole("link", { name: CUSTOMER }).first().click();
    await expect(page).toHaveURL(new RegExp(seeded.workspace.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "(\\?|#|$)"));
    await expect(page.getByRole("heading", { name: new RegExp(CUSTOMER) })).toBeVisible();

    // Tafel: zwei Produkte, drei offene Perioden, die passende Rechnung steht an ihrer Periode.
    await expect(page.getByText(EXCHANGE).first()).toBeVisible();
    await expect(page.getByText(STANDARD).first()).toBeVisible();
    await expect(tile(page, "Offen")).toContainText("3");
    const assign = page.getByRole("main").getByRole("button", { name: /zuordnen$/ }).first();
    await expect(assign).toBeVisible();
    await assign.click();

    await expect(page.getByText(`Position ${seeded.invoice} zugeordnet.`)).toBeVisible();
    await expect(tile(page, "Offen")).toContainText("2");
    await expect(page.getByRole("main").getByRole("button", { name: /zuordnen$/ })).toHaveCount(0);

    // Zählkachel „Offen“ filtert die Tafel auf das Exchange-Produkt; ein zweiter Klick hebt den Filter auf.
    await tile(page, "Offen").click();
    await expect(page).toHaveURL(/focus=open/);
    await expect(page.getByText(EXCHANGE).first()).toBeVisible();
    await expect(page.getByText(STANDARD)).toHaveCount(0);
    await expect(page.getByRole("link", { name: "Alle Produkte zeigen" })).toBeVisible();
    await tile(page, "Offen").click();
    await expect(page).not.toHaveURL(/focus=/);
    await expect(page.getByText(STANDARD).first()).toBeVisible();

    // Rechnungsentwurf aus der Mappe (Seitenkopf liegt außerhalb von <main>): Empfänger vorgewählt,
    // zwei offene Perioden, lokaler Entwurf.
    await page.getByRole("link", { name: "Rechnungsentwurf" }).click();
    const dialog = page.locator("dialog[open]#entry-modal");
    await expect(dialog.getByRole("heading", { name: /Rechnungsentwurf/ })).toBeVisible();
    await page.waitForTimeout(400);
    const recipient = dialog.locator('select[name="customer_id"]');
    await expect(recipient.locator("option:checked")).toContainText(CUSTOMER);
    await expect(recipient.locator("option:checked")).toContainText("2 ");
    await dialog.getByRole("button", { name: "Entwurf anlegen" }).click();

    await expect(page).toHaveURL(/\/finanzen\/abos\/perioden/);
    await expect(page.getByText(new RegExp(`Rechnungsentwurf .* für ${CUSTOMER} angelegt`))).toBeVisible();
});

test("Abo-Import: generische Liste bis zur Vorschau, dann verwerfen", async ({ page }) => {
    await page.goto("/finanzen/abos");
    await page.locator('a[data-entry-modal-trigger][href$="/abos/import"]').first().click();
    const dialog = page.locator("dialog[open]#entry-modal");
    await expect(dialog.getByRole("heading", { name: "Anbieter-Exporte importieren" })).toBeVisible();
    await page.waitForTimeout(400);

    const csv = [
        "Kennung;Firma;Produkt;Menge;Beginn;Ende;Intervall;Laufzeit (Monate);Einkaufspreis;Verkaufspreis;Anbieter;Bestellnummer",
        `V-E2E-0001;${CUSTOMER};${STANDARD};2;01.03.2026;;jährlich;12;128,38;145,56;manual;`,
    ].join("\n");
    await dialog.locator('input[name="generic"]').setInputFiles({ name: "abos.csv", mimeType: "text/csv", buffer: Buffer.from("﻿" + csv + "\n", "utf8") });
    await dialog.locator('select[name="generic_provider"]').selectOption("other");
    await dialog.getByRole("button", { name: "Importieren" }).click();

    // Die Vorschau ist eine Seite (kein Dialog-Fragment): nichts geschrieben, Übernehmen und Verwerfen stehen bereit.
    await expect(page.getByText("Was der Import ändern würde")).toBeVisible();
    await expect(page.getByRole("heading", { name: /abos\.csv/ })).toBeVisible();
    await expect(page.getByRole("main").getByText("Neu", { exact: true }).first()).toBeVisible();
    await expect(page.getByRole("main").getByText(STANDARD).first()).toBeVisible();
    // „Übernehmen“ gibt es auch am Zeitraum im Seitenkopf — nur die Seitenaktionen zählen;
    // „Verwerfen“ steht als gefährliche Aktion im ⋯-Menü der Toolbar.
    const toolbar = page.locator("[data-toolbar]");
    await expect(toolbar.getByRole("button", { name: "Übernehmen" })).toBeVisible();
    await toolbar.locator("[data-toolbar-more] summary").click();
    await toolbar.locator("[data-toolbar-menu-danger]").getByRole("button", { name: "Verwerfen" }).click();
    await expect(page).toHaveURL(/\/finanzen\/abos(\?|$)/);
    await expect(page.locator('a[data-entry-modal-trigger][href$="/abos/import"]').first()).toBeVisible();
});

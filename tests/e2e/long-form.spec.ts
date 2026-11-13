/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : long-form.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Lange Formulare (Feature 203, MVP-1649–1651): Ein Bogen mit fünf
 * Abschnitten öffnet als großer Dialog mit Inhaltsleiste; nach einer Antwort
 * steigt der Zähler ohne Neuladen; die Tab-Taste führt in Lesereihenfolge von
 * Feld zu Feld, eine Auswahl mit vier Möglichkeiten ist eine Knopfgruppe mit
 * Pfeiltasten; jedes verlassene Feld ist gespeichert — nach dem Neuladen steht
 * der Wert wieder da, die Einsendung bleibt Entwurf bis zum Absenden.
 */

import { test, expect, type Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const TEMPLATE = "E2E Erstaufnahme";
const DIALOG = "dialog[open]#entry-modal";

/** Aktive Vorlage mit fünf Abschnitten à zwei Fragen (erfundene Inhalte). */
function seedTemplate(): void {
    const sections = [1, 2, 3, 4, 5]
        .map(
            (n) => `
            ['key' => 'abschnitt_${n}', 'label' => 'Abschnitt ${n}', 'type' => 'section', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'frage_${n}', 'label' => 'Frage ${n}', 'type' => 'text', 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'wahl_${n}', 'label' => 'Wahl ${n}', 'type' => 'choice', 'required' => false, 'options' => ['ja', 'nein', 'teilweise', 'unklar'], 'help' => null, 'unit' => null],`,
        )
        .join("");
    const code = `
        $user = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->firstOrFail();
        app()->instance('currentOrganization', $user->organization);
        \\App\\Models\\Form\\FormSubmission::withoutGlobalScopes()->where('organization_id', $user->organization_id)->forceDelete();
        \\App\\Models\\Form\\FormTemplate::withoutGlobalScopes()->where('name', '${TEMPLATE}')->get()->each(fn ($t) => $t->forceDelete());
        \\App\\Models\\Form\\FormTemplate::factory()->active()->create([
            'organization_id' => $user->organization_id,
            'created_by_user_id' => $user->id,
            'name' => '${TEMPLATE}',
            'fields' => [${sections}],
        ]);
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
    if (!out.includes("OK")) throw new Error(`Vorlage nicht angelegt:\n${out}`);
}

async function openForm(page: Page) {
    await page.goto("/form-submissions");
    await page.locator("summary", { hasText: "Formular ausfüllen" }).first().click();
    await page.getByRole("link", { name: TEMPLATE }).click();
    const dialog = page.locator(DIALOG);
    await expect(dialog.getByRole("heading", { name: `Formular ausfüllen: ${TEMPLATE}` })).toBeVisible();
    // daisyUI blendet die Box mit einer 0,3-s-Transition ein.
    await page.waitForTimeout(400);
    return dialog;
}

test.beforeAll(() => {
    seedTemplate();
});

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
});

test("Fünf Abschnitte: Inhaltsleiste, Zähler ohne Neuladen, Tab-Folge und Knöpfe per Tastatur", async ({ page }) => {
    const dialog = await openForm(page);

    await expect(dialog.getByRole("navigation", { name: "Inhalt des Formulars" })).toBeVisible();
    const firstCard = dialog.locator("#fld-values-sec-abschnitt_1");
    await expect(firstCard.locator(".wd-field-card__count")).toHaveText("0 von 2 beantwortet");

    // Tab-Folge: Textfeld → Knopfgruppe (ein Tabstopp) → nächstes Textfeld.
    const text1 = dialog.locator('input[name="values[frage_1]"]');
    await text1.focus();
    await text1.fill("Antwort eins");
    await page.keyboard.press("Tab");
    await expect(firstCard.locator(".wd-field-card__count")).toHaveText("1 von 2 beantwortet");
    await expect(dialog.locator('input[name="values[wahl_1]"]').first()).toBeFocused();

    // Pfeiltasten wählen in der Knopfgruppe.
    await page.keyboard.press("ArrowRight");
    await expect(dialog.locator('input[name="values[wahl_1]"][value="nein"]')).toBeChecked();
    await expect(firstCard.locator(".wd-field-card__count")).toHaveText("2 von 2 beantwortet");
    await page.keyboard.press("Tab");
    await expect(dialog.locator('input[name="values[frage_2]"]')).toBeFocused();

    // Punkt der Frage ist beantwortet, ohne dass der Dialog neu aufgebaut wurde.
    await expect(dialog.locator('[data-field-dot="frage_1"]')).toHaveClass(/wd-field-dot--done/);
});

test("Verlassene Felder sind gespeichert; nach dem Neuladen steht der Wert da, der Bogen bleibt Entwurf", async ({ page }) => {
    let dialog = await openForm(page);

    await dialog.locator('input[name="values[frage_3]"]').fill("Zwischenstand");
    await dialog.locator('input[name="values[frage_4]"]').focus();
    await dialog.locator('input[name="values[frage_4]"]').fill("Gleich danach");
    await page.keyboard.press("Tab");
    await expect(dialog.getByRole("status").filter({ hasText: "Alle Antworten gespeichert." })).toBeVisible();

    await page.reload();
    await expect(page.getByText("Meine Entwürfe")).toBeVisible();
    await page.getByRole("link", { name: "Weiter ausfüllen" }).first().click();
    dialog = page.locator(DIALOG);
    await expect(dialog.locator('input[name="values[frage_3]"]')).toHaveValue("Zwischenstand");
    await expect(dialog.locator('input[name="values[frage_4]"]')).toHaveValue("Gleich danach");
});

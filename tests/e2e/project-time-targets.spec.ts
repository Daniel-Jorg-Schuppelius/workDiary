/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : project-time-targets.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Sollzeiten-Raster am Projekt (Feature 194, MVP-1467/1471): Wert auf alle
 * markierten Personen und Wochentage anwenden, speichern, Kacheln am
 * Zeitreiter, Werte beim erneuten Öffnen. Prüft die Alpine-Komponente
 * timeTargetGrid im CSP-Build und den Dialog-Rundlauf im Browser.
 */

import { test, expect } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const STAMP = Date.now();
const PROJECT = `E2E Sollzeiten ${STAMP}`;

function seedProject(): string {
    const code = `
        $org = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id');
        $a = \\App\\Models\\Platform\\User::factory()->user()->create(['organization_id' => $org, 'name' => 'E2E Anna ${STAMP}']);
        $b = \\App\\Models\\Platform\\User::factory()->user()->create(['organization_id' => $org, 'name' => 'E2E Ben ${STAMP}']);
        $p = \\App\\Models\\Project\\Project::factory()->create(['organization_id' => $org, 'name' => '${PROJECT}']);
        $p->members()->attach([$a->id, $b->id]);
        echo 'URL=' . route('projects.show', ['project' => $p, 'tab' => 'time']) . "\\n";
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
    const match = out.match(/URL=(\S+)/);
    if (!match) throw new Error(`Projekt nicht angelegt:\n${out}`);
    return new URL(match[1]).pathname + "?tab=time";
}

let timeTabPath = "";

test.beforeAll(() => {
    timeTabPath = seedProject();
});

test("Sollzeiten-Raster: Wert auf markierte Personen anwenden, speichern, wieder öffnen", async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 1000 });
    await page.goto(timeTabPath);
    await page.locator('a[data-entry-modal-trigger][href$="/time-targets"]').filter({ visible: true }).first().click();

    const form = page.locator("dialog[open] form[data-entry-form]");
    await expect(form.locator('input[name="default[1]"]')).toBeVisible();

    await form.locator('input[name="default[1]"]').fill("8:00");
    await form.locator("[data-ttg-value]").fill("6:00");
    await form.locator("[data-ttg-all]").check();
    await form.getByRole("button", { name: "Auf ausgewählte Personen anwenden" }).click();

    const rows = form.locator("tr[data-ttg-row]");
    await expect(rows).toHaveCount(2);
    await expect(rows.nth(0).locator('input[data-ttg-cell="1"]')).toHaveValue("6:00");
    await expect(rows.nth(1).locator('input[data-ttg-cell="5"]')).toHaveValue("6:00");
    await expect(rows.nth(1).locator('input[data-ttg-cell="6"]')).toHaveValue("", { timeout: 2000 });

    // Der Fußzeilen-Knopf des Modals liegt außerhalb des Formular-Elements.
    await page.locator("dialog[open]").getByRole("button", { name: "Speichern" }).click();
    await expect(page.locator("dialog[open] form[data-entry-form]")).toHaveCount(0);
    await expect(page.getByText("Soll im Zeitraum")).toBeVisible();

    await page.locator('a[data-entry-modal-trigger][href$="/time-targets"]').filter({ visible: true }).first().click();
    const again = page.locator("dialog[open] form[data-entry-form]");
    await expect(again.locator('input[name="default[1]"]')).toHaveValue("8:00");
    await expect(again.locator("tr[data-ttg-row]").nth(0).locator('input[data-ttg-cell="3"]')).toHaveValue("6:00");
});

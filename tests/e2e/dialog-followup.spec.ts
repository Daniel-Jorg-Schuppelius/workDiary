/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dialog-followup.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Folgedialog mit Rücksprung (Pattern-Katalog §3.3, GitHub #106): aus dem
 * Mitarbeiter-Dialog öffnet „Arbeitszeit-Modell bearbeiten“ als Folgedialog;
 * Speichern kehrt in die Ursprungsmaske zurück (Eingaben bleiben, der
 * Wochenstunden-Block ist aufgefrischt), Abbrechen und Escape ebenso — der
 * ganze Vorgang schließt sich erst mit der Ursprungsmaske.
 */

import { test, expect, type Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const MEMBER = "Mira Muster";
const MEMBER_EMAIL = "mira.muster@example.test";

function seedMember(): void {
    const code = `
        $org = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id');
        \\App\\Models\\Platform\\User::withoutGlobalScopes()->where('email', '${MEMBER_EMAIL}')->get()->each(fn ($u) => $u->forceDelete());
        \\App\\Models\\Platform\\User::factory()->user()->create(['organization_id' => $org, 'name' => '${MEMBER}', 'email' => '${MEMBER_EMAIL}']);
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
    if (!out.includes("OK")) throw new Error(`Mitarbeiter nicht angelegt:\n${out}`);
}

const DIALOG = "dialog[open]#entry-modal";

async function openMemberDialog(page: Page) {
    await page.goto("/org/members");
    const row = page.locator("tr", { hasText: MEMBER });
    await row.locator('a[data-entry-modal-trigger][href$="/edit"]').click();
    const dialog = page.locator(DIALOG);
    await expect(dialog.getByRole("heading", { name: "Mitarbeiter bearbeiten" })).toBeVisible();
    // daisyUI blendet die Box mit einer 0,3-s-Transition ein.
    await page.waitForTimeout(400);
    return dialog;
}

async function openFollowup(page: Page) {
    const dialog = page.locator(DIALOG);
    await dialog.getByRole("link", { name: "Arbeitszeit-Modell bearbeiten" }).click();
    await expect(dialog.getByRole("heading", { name: "Arbeitszeit-Modell", exact: true })).toBeVisible();
    // Die Ursprungsmaske ist abgehängt, nicht nur überdeckt.
    await expect(dialog.getByRole("heading", { name: "Mitarbeiter bearbeiten" })).toHaveCount(0);
}

/**
 * notifyAction ist ein Modal (#action-notify-dialog), kein Toast — es blockiert
 * Klicks. Es erscheint erst mit der Antwort des Servers, also auf sein Öffnen warten.
 */
async function closeNotice(page: Page, message: string | RegExp) {
    const notice = page.locator("dialog[open]#action-notify-dialog");
    await expect(notice).toBeVisible();
    await expect(notice).toContainText(message);
    await notice.locator("#action-notify-ok").click();
    await expect(notice).toHaveCount(0);
}

test.beforeAll(() => {
    seedMember();
});

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
});

test("Speichern im Folgedialog kehrt in die Mitarbeitermaske zurück und frischt die Wochenstunden auf", async ({ page }) => {
    const dialog = await openMemberDialog(page);

    // Eingabe in der Ursprungsmaske: sie muss den Ausflug überleben.
    await dialog.locator('input[name="personnel_number"]').fill("E2E-4711");
    await openFollowup(page);

    await dialog.locator("#weekly_minutes").fill("2100");
    await dialog.getByRole("button", { name: "Speichern" }).click();
    await closeNotice(page, "Arbeitszeit-Modell gespeichert.");

    await expect(dialog.getByRole("heading", { name: "Mitarbeiter bearbeiten" })).toBeVisible();
    await expect(dialog.locator('input[name="personnel_number"]')).toHaveValue("E2E-4711");
    await expect(dialog.locator("#weekly-hours-display")).toHaveValue(/35,00 h/);
    await expect(dialog.getByRole("button", { name: "Speichern" })).toBeVisible();
    await expect(dialog.getByRole("button", { name: "Abbrechen" })).toBeVisible();

    // Fehlerfall im Folgedialog bleibt im Folgedialog (422), erst Abbrechen führt zurück.
    await openFollowup(page);
    await dialog.locator("#break_after_minutes").fill("1");
    await dialog.getByRole("button", { name: "Speichern" }).click();
    await expect(dialog.getByRole("heading", { name: "Arbeitszeit-Modell", exact: true })).toBeVisible();
    await dialog.getByRole("button", { name: "Abbrechen" }).click();
    await expect(dialog.getByRole("heading", { name: "Mitarbeiter bearbeiten" })).toBeVisible();
    await expect(dialog.locator("#weekly-hours-display")).toHaveValue(/35,00 h/);
});

test("Abbrechen und Escape im Folgedialog führen zurück, nicht hinaus", async ({ page }) => {
    const dialog = await openMemberDialog(page);

    await openFollowup(page);
    await dialog.getByRole("button", { name: "Abbrechen" }).click();
    await expect(dialog.getByRole("heading", { name: "Mitarbeiter bearbeiten" })).toBeVisible();
    await expect(page.locator("dialog[open]")).toHaveCount(1);

    await openFollowup(page);
    await page.keyboard.press("Escape");
    await expect(dialog.getByRole("heading", { name: "Mitarbeiter bearbeiten" })).toBeVisible();
    await expect(page.locator("dialog[open]")).toHaveCount(1);

    // Erst die Ursprungsmaske schließt den Vorgang.
    await page.keyboard.press("Escape");
    await expect(page.locator("dialog[open]")).toHaveCount(0);
});

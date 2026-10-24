/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : task-import.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Verbindungsseite der Aufgabenimporte (Phase 157, MVP-1311) an Jira: bei
 * Desktop- und Telefonbreite ohne waagerechten Überlauf, Zeilenaktionen mit
 * zugänglichem Namen, Abgleich und Trennen im ⋯-Menü, per Tastatur
 * erreichbar. Die Verbindung wird direkt angelegt — die Seite selbst ruft
 * Jira nicht ab.
 */

import { test, expect, type Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

function seedJiraConnection(): void {
    const code = `
        $org = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id');
        \\App\\Models\\Platform\\PluginSetting::query()->updateOrCreate(['organization_id' => $org, 'plugin_id' => 'jira'], ['enabled' => true, 'settings' => []]);
        \\App\\Plugins\\Jira\\Models\\JiraConnection::query()->withoutGlobalScopes()->where('organization_id', $org)->delete();
        \\App\\Plugins\\Jira\\Models\\JiraConnection::query()->forceCreate([
            'organization_id' => $org, 'source_id' => 'a1b2c3d4-0000-4000-8000-000000000001', 'source_name' => 'Muster GmbH',
            'source_url' => 'https://muster.atlassian.net', 'available_sources' => [['id' => 'a1b2c3d4-0000-4000-8000-000000000001', 'name' => 'Muster GmbH', 'url' => 'https://muster.atlassian.net']],
            'access_token' => 'e2e', 'refresh_token' => 'e2e', 'token_expires_at' => now()->addDay(), 'status' => 'active', 'connected_at' => now(),
        ]);
        $project = \\App\\Models\\Project\\Project::factory()->create(['organization_id' => $org, 'name' => 'Portal-Relaunch']);
        \\App\\Plugins\\Jira\\Models\\JiraProjectLink::query()->withoutGlobalScopes()->where('organization_id', $org)->delete();
        \\App\\Plugins\\Jira\\Models\\JiraProjectLink::query()->forceCreate([
            'organization_id' => $org, 'source_project_id' => '10000', 'source_project_key' => 'PORTAL',
            'source_project_name' => 'Kundenportal mit sehr langem Projektnamen für schmale Bildschirme',
            'project_id' => $project->id, 'status' => 'active', 'import_confirmed_at' => now(),
            'last_run_at' => now(), 'is_last_run_complete' => true,
            'last_run_counters' => ['created' => 12, 'updated' => 3, 'unchanged' => 40, 'conflicts' => 1, 'inbox' => 2, 'failed' => 0],
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
    if (!out.includes("OK")) throw new Error(`Jira-Verbindung nicht angelegt:\n${out}`);
}

async function expectNoHorizontalScroll(page: Page) {
    const widths = await page.evaluate(() => ({ doc: document.documentElement.scrollWidth, view: window.innerWidth }));
    expect(widths.doc).toBeLessThanOrEqual(widths.view + 1);
}

test.beforeAll(() => {
    seedJiraConnection();
});

for (const width of [1280, 390]) {
    test(`Jira-Seite bei ${width} px: kein Überlauf, Zuordnung mit Aktionen`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto("/admin/jira");

        await expect(page.getByText("Kundenportal mit sehr langem Projektnamen")).toBeVisible();
        await expect(page.getByText("Portal-Relaunch")).toBeVisible();
        await expectNoHorizontalScroll(page);

        for (const name of ["Vorschau", "Pausieren", "Zuordnung entfernen"]) {
            await expect(page.getByRole("main").getByRole("button", { name }).or(page.getByRole("main").getByRole("link", { name }))).toHaveCount(1);
        }

        const more = page.locator("[data-toolbar] [data-toolbar-more]");
        await more.locator("summary").click();
        await expect(more.getByRole("button", { name: "Jetzt abgleichen" })).toBeVisible();
        await expect(more.locator("[data-toolbar-menu-danger]").getByRole("button", { name: "Trennen" })).toBeVisible();
        const menu = await more.locator(".wd-toolbar-menu").boundingBox();
        expect((menu?.x ?? 0) + (menu?.width ?? 0)).toBeLessThanOrEqual(width);
    });
}

test("Tastatur: Menü öffnen, Trennen fragt nach, Escape bricht ab", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto("/admin/jira");

    const summary = page.locator("[data-toolbar] [data-toolbar-more] summary");
    await summary.focus();
    await page.keyboard.press("Enter");
    const disconnect = page.locator("[data-toolbar-menu-danger]").getByRole("button", { name: "Trennen" });
    await disconnect.focus();
    await page.keyboard.press("Enter");
    await expect(page.locator("#action-confirm-dialog")).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.locator("#action-confirm-dialog")).toBeHidden();
    await expect(page.getByText("Portal-Relaunch")).toBeVisible();
});

/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : toolbar-overflow.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Seitenkopf mit Überlaufmenü (MVP-966) an der Rechnung im Entwurf: bei
 * jeder Breite einzeilig ohne Überlauf, „Stellen“ bleibt stehen, Löschen
 * steht im ⋯-Menü, und aus dem Menü heraus arbeiten Modal-Trigger und
 * Bestätigungsdialog wie in der Leiste.
 */

import { test, expect, type Page } from "@playwright/test";
import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Entwurf mit einer Position für die Organisation des Test-Admins; liefert den Pfad. */
function createDraftInvoice(): string {
    const code = `
        $org = \\App\\Models\\Platform\\User::where('email', 'test@example.com')->value('organization_id');
        $customer = \\App\\Models\\Customer\\Customer::factory()->create(['organization_id' => $org, 'name' => 'Peter Lustig']);
        $invoice = \\App\\Models\\Invoicing\\Invoice::factory()->create(['organization_id' => $org, 'customer_id' => $customer->id]);
        \\App\\Models\\Invoicing\\InvoiceItem::factory()->create(['organization_id' => $org, 'invoice_id' => $invoice->id]);
        echo 'PATH=' . parse_url(route('invoices.show', $invoice), PHP_URL_PATH);
    `;
    const out = execFileSync("php", ["artisan", "tinker", `--execute=${code}`], {
        cwd: ROOT,
        env: {
            ...process.env,
            DB_DATABASE: path.join(ROOT, "database", "testing.sqlite-ui"),
            CACHE_STORE: "array",
            APP_INSTALLED: "true",
        },
        encoding: "utf8",
    });
    const match = out.match(/PATH=(\S+)/);
    if (!match) throw new Error(`Rechnung nicht angelegt:\n${out}`);
    return match[1];
}

const TOOLBAR = "[data-toolbar]";
const MORE = `${TOOLBAR} [data-toolbar-more]`;

async function expectNoOverflow(page: Page) {
    const box = await page.locator(TOOLBAR).evaluate((el) => ({
        scroll: el.scrollWidth,
        client: el.clientWidth,
        right: el.getBoundingClientRect().right,
        actionsRight: el.querySelector("[data-toolbar-actions]")?.getBoundingClientRect().right ?? 0,
    }));
    expect(box.scroll).toBeLessThanOrEqual(box.client + 1);
    expect(box.actionsRight).toBeLessThanOrEqual(box.right + 1);
}

let invoicePath = "";

test.beforeAll(() => {
    invoicePath = createDraftInvoice();
});

for (const width of [1600, 1280, 1024, 390, 360]) {
    test(`Rechnung im Entwurf bei ${width} px: einzeilig, Stellen sichtbar, Löschen im Menü`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(invoicePath);

        const bar = page.locator(`${TOOLBAR} [data-toolbar-actions]`);
        await expect(page.locator(TOOLBAR)).toHaveClass(/wd-toolbar-js/);
        await expectNoOverflow(page);

        await expect(bar.getByRole("button", { name: "Stellen" })).toBeVisible();
        await expect(page.locator(`${MORE} summary`)).toBeVisible();
        await expect(page.getByRole("button", { name: "Löschen" })).toBeHidden();

        await page.locator(`${MORE} summary`).click();
        await expect(page.locator(`${MORE} [data-toolbar-menu-danger]`).getByRole("button", { name: "Löschen" })).toBeVisible();

        const menu = await page.locator(`${MORE} .wd-toolbar-menu`).boundingBox();
        expect(menu?.x).toBeGreaterThanOrEqual(0);
        expect((menu?.x ?? 0) + (menu?.width ?? 0)).toBeLessThanOrEqual(width);
    });
}

test("aus dem Menü: Bestätigungsdialog und Modal-Trigger arbeiten weiter", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(invoicePath);

    await page.locator(`${MORE} summary`).click();
    await page.locator(`${MORE}`).getByRole("button", { name: "Löschen" }).click();
    await expect(page.locator("#action-confirm-dialog")).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.locator("#action-confirm-dialog")).toBeHidden();

    await page.locator(`${MORE} summary`).click();
    await page.locator(`${MORE}`).getByRole("link", { name: "Konditionen" }).click();
    await expect(page.locator("dialog[open]")).toHaveCount(1);
    await expect(page.locator(`${MORE}`)).not.toHaveAttribute("open", "");
});

test("Tastatur: Enter öffnet, Escape schließt und gibt den Fokus zurück", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(invoicePath);

    const summary = page.locator(`${MORE} summary`);
    await summary.focus();
    await page.keyboard.press("Enter");
    await expect(page.locator(MORE)).toHaveAttribute("open", "");
    await page.keyboard.press("Escape");
    await expect(page.locator(MORE)).not.toHaveAttribute("open", "");
    await expect(summary).toBeFocused();
});

test("breiter Bildschirm holt Aktionen aus dem Menü zurück", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(invoicePath);
    const inMenuNarrow = await page.locator(`${MORE} [data-toolbar-menu-main] > *`).count();

    await page.setViewportSize({ width: 1600, height: 900 });
    await expect
        .poll(async () => page.locator(`${MORE} [data-toolbar-menu-main] > *`).count())
        .toBeLessThan(inMenuNarrow);
    await expectNoOverflow(page);
});

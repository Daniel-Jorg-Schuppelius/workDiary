/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline-replay.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Offline-Replay-Smoke: Die Outbox (offline-sync.js) ist bewusst OHNE
 * Service-Worker-Request-Replay gebaut — deshalb reicht hier
 * context.setOffline(): das Formular wird abgefangen, in IndexedDB
 * geparkt und beim online-Event gegen api.internal.sync.commands geflusht.
 */

import { test, expect } from "@playwright/test";
import { clockInSubmit, clockOutSubmit, ensureClockedOut } from "./helpers";

test("Offline-Stempelung landet in der Outbox und wird nach Reconnect angewendet", async ({
    page,
    context,
}) => {
    await page.goto("/today");
    await ensureClockedOut(page);

    // Offline gehen → das offline-Event zeigt den Sync-Badge an.
    await context.setOffline(true);
    const badge = page.locator("[data-sync-status]");

    // Einstempeln offline: KEIN Navigationsversuch — der Submit wird
    // abgefangen und als Befehl in der IndexedDB-Outbox gespeichert.
    await clockInSubmit(page).click();
    await expect(badge).toBeVisible();
    await expect(badge.locator("[data-sync-pending-count]")).toHaveText("1");

    // Reconnect: online-Event → Flush → Badge räumt sich.
    await context.setOffline(false);
    await expect(badge).toBeHidden({ timeout: 15_000 });

    // Der Befehl ist serverseitig angewendet: die Uhr läuft.
    await page.reload();
    await expect(clockOutSubmit(page)).toBeVisible();

    // Aufräumen: wieder ausstempeln, damit Folgeläufe sauber starten.
    await clockOutSubmit(page).click();
    await expect(clockInSubmit(page)).toBeVisible();
});

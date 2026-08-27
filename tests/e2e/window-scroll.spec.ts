/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : window-scroll.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

/**
 * Die App-Shell scrollt ausschließlich innen: der Seitenkopf steht, der Body
 * der Page-Shell rollt. Ein Scrollbalken am Fensterrand ist damit immer ein
 * Fehler — er führt in einen leeren Bereich unter der Seite.
 *
 * Typische Ursache: ein `position: absolute`-Element (z. B. `sr-only`) in
 * einer langen Liste. Scrollcontainer clippen absolut positionierte Nachfahren
 * nur, wenn sie selbst positioniert sind; die Page-Shell ist `static`, also
 * brechen solche Elemente aus und blähen die Dokumenthöhe auf.
 */
const PAGES = [
    "/dashboard",
    "/me/dashboard/customize",
    "/me/navigation/customize",
];

for (const url of PAGES) {
    test(`kein Fenster-Scrollbalken auf ${url}`, async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(url);
        await page.waitForLoadState("networkidle");

        const overflow = await page.evaluate(
            () => document.documentElement.scrollHeight - document.documentElement.clientHeight,
        );

        expect(overflow, `${url} erzeugt ${overflow}px Fenster-Scroll`).toBeLessThanOrEqual(0);
    });
}

/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webauthn.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

test("Passkey-Registrierung: UI und Options-Endpunkt (ohne echten Authenticator)", async ({
    page,
}) => {
    await page.goto("/account/two-factor");

    const block = page.locator("[data-webauthn-block]");
    await expect(block).toBeVisible();

    const registerBtn = block.locator("[data-webauthn-register]").first();
    await expect(registerBtn).toBeVisible();
    await expect(registerBtn).toHaveAttribute(
        "data-options",
        /two-factor\/webauthn\/options$/,
    );
    await expect(registerBtn).toHaveAttribute(
        "data-target",
        /two-factor\/webauthn$/,
    );

    // Server-Smoke: die Registrierungs-Options (Challenge) kommen —
    // navigator.credentials.create() selbst braucht einen Authenticator
    // und bleibt bewusst außen vor.
    const optionsUrl = await registerBtn.getAttribute("data-options");
    const xsrf = (await page.context().cookies()).find(
        (c) => c.name === "XSRF-TOKEN",
    )?.value;
    const response = await page.request.post(String(optionsUrl), {
        headers: { "X-XSRF-TOKEN": decodeURIComponent(String(xsrf)) },
    });
    expect(response.ok()).toBeTruthy();
});

/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : global-setup.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Erzeugt die Org-Admin-Session (test@example.com aus dem DatabaseSeeder)
 * als storageState für alle Specs. Wichtig: frischer Login landet im
 * Legacy-Modus und ALLE neuen Seiten leiten auf /legacy/diary um — daher
 * direkt nach dem Login POST /mode/new (CSRF via XSRF-TOKEN-Cookie).
 */

import { request, type FullConfig } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

export const ORG_ADMIN_STATE = "tests/e2e/.auth/org-admin.json";

export default async function globalSetup(config: FullConfig) {
    const { baseURL } = config.projects[0].use;
    if (!baseURL) throw new Error("baseURL fehlt in der Playwright-Config.");

    fs.mkdirSync(path.dirname(ORG_ADMIN_STATE), { recursive: true });

    const ctx = await request.newContext({ baseURL });

    // Login-Formular holen → CSRF-Token (Feld heißt _token, Login-Feld username).
    const loginPage = await ctx.get("/login");
    const html = await loginPage.text();
    const token = html.match(/name="_token"\s+value="([^"]+)"/)?.[1];
    if (!token) throw new Error("Kein CSRF-Token auf /login gefunden.");

    const login = await ctx.post("/login", {
        form: { _token: token, username: "test@example.com", password: "password" },
    });
    if (!login.ok()) {
        throw new Error(`Login fehlgeschlagen: HTTP ${login.status()}`);
    }

    // work_mode-Falle: in den neuen Modus wechseln. CSRF über das
    // XSRF-TOKEN-Cookie (URL-encodiert) als X-XSRF-TOKEN-Header.
    const xsrf = (await ctx.storageState()).cookies.find(
        (c) => c.name === "XSRF-TOKEN",
    )?.value;
    if (!xsrf) throw new Error("Kein XSRF-TOKEN-Cookie nach dem Login.");

    const mode = await ctx.post("/mode/new", {
        headers: { "X-XSRF-TOKEN": decodeURIComponent(xsrf) },
    });
    if (!mode.ok()) {
        throw new Error(`POST /mode/new fehlgeschlagen: HTTP ${mode.status()}`);
    }

    await ctx.storageState({ path: ORG_ADMIN_STATE });
    await ctx.dispose();
}

/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : playwright.config.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * E2E-Grundbestand (Vollscan 2026-08-23, D4). Der webServer seedet eine
 * eigene SQLite-DB (database/testing.sqlite-ui, gitignored) und startet
 * `php artisan serve` — siehe tests/e2e/server.sh. Die LEGACY_-Overrides
 * zeigen bewusst auf einen toten Port: Seiten dürfen nicht an einer
 * Live-Legacy-DB hängen, und /legacy/* wird nie beschrieben.
 *
 * Lauf: npm run test:e2e   (CI: nicht-blockierender Job "e2e").
 */

import { defineConfig } from "@playwright/test";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.E2E_PORT || 8010);
const BASE_URL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
    testDir: "tests/e2e",
    globalSetup: "./tests/e2e/global-setup",
    outputDir: "test-results",
    timeout: 30_000,
    expect: { timeout: 10_000 },
    // Eine gemeinsame SQLite-DB + WSL2-RAM: bewusst sequentiell.
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [["list"], ["github"]] : [["list"]],
    use: {
        baseURL: BASE_URL,
        // Org-Admin-Session (test@example.com) inkl. work_mode=new —
        // erzeugt vom globalSetup. Der Login-Spec setzt storageState leer.
        storageState: "tests/e2e/.auth/org-admin.json",
        locale: "de-DE",
        timezoneId: "Europe/Berlin",
        trace: "retain-on-failure",
        screenshot: "only-on-failure",
    },
    webServer: {
        command: "bash tests/e2e/server.sh",
        url: `${BASE_URL}/login`,
        reuseExistingServer: !process.env.CI,
        // migrate:fresh --seed läuft mit im Kommando.
        timeout: 300_000,
        env: {
            E2E_PORT: String(PORT),
            DB_DATABASE: path.join(ROOT, "database", "testing.sqlite-ui"),
            CACHE_STORE: "array",
            SESSION_DRIVER: "file",
            // Toter Port statt Live-Legacy-DB (siehe Kopfkommentar).
            LEGACY_DB_HOST: "127.0.0.1",
            LEGACY_DB_PORT: "65531",
            APP_INSTALLED: "true",
            PHP_CLI_SERVER_WORKERS: "8",
        },
    },
});

/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

test("Kanban-Board rendert Spalten", async ({ page }) => {
    await page.goto("/kanban");
    await expect(page.locator("[data-kanban-board]")).toBeVisible();
    expect(await page.locator("[data-kanban-column]").count()).toBeGreaterThan(2);
});

test("Kanban Drag&Drop verschiebt Karten workflow-konform", async () => {
    // Bewusst übersprungen: Die Züge laufen nicht über einen simplen
    // Positions-Write, sondern über diary.lifecycle-POSTs mit
    // Workflow-Prüfung (Kanban-DnD workflow-konform) — das eigentliche
    // Verhalten ist serverseitig per PHPUnit abgedeckt. Die DnD-Geste
    // selbst (kanban.js, HTML5-Drag-Events) lässt sich mit synthetischen
    // Playwright-Events nicht stabil auslösen (dragstart/dragover/drop
    // mit DataTransfer über Spaltengrenzen); ein wackeliger Spec wäre
    // schlechter als keiner. Kandidat für später: mouse.down/move/up,
    // falls kanban.js auf Pointer-Events umgestellt wird.
    test.skip(true, "DnD-Simulation instabil; Zug-Logik serverseitig getestet (diary.lifecycle)");
});

/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : layout-state.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

/**
 * Gemerkte Sidebar-Zustände (Menü eingeklappt, Hilfe offen) setzen
 * Inline-Skripte vor dem ersten Rendern. Kämen sie aus den Modulskripten,
 * liefe nach jedem Seitenwechsel die Breiten-Transition sichtbar ab: die
 * Hilfe klappt zu und wieder auf.
 */
const WATCHED = [
    "#help-drawer",
    "#app-sidebar",
    "[data-help-main]",
    "[data-help-railmode]",
    ".with-help-pad",
    ".with-sidebar-pad",
    "[data-sidebar-collapse-icon]",
    "[data-help-footer-chevron]",
].join(", ");

test("gemerkte Sidebar-Zustände stehen nach dem Seitenwechsel ohne Transition", async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/dashboard");
    await page.keyboard.press("F1");
    await expect(page.locator("body")).toHaveClass(/help-sidebar-open/);
    await page.locator("#app-sidebar-collapse").click();
    await expect(page.locator("body")).toHaveClass(/sidebar-collapsed/);

    await page.addInitScript((watched) => {
        const runs: string[] = [];
        (window as unknown as { __layoutTransitions: string[] }).__layoutTransitions = runs;
        document.addEventListener(
            "transitionrun",
            (event) => {
                const target = event.target as Element;
                if (target.matches?.(watched)) {
                    runs.push(`${target.id || target.className}: ${(event as TransitionEvent).propertyName}`);
                }
            },
            true,
        );
    }, WATCHED);

    await page.goto("/me/navigation/customize");
    await page.waitForLoadState("networkidle");

    await expect(page.locator("body")).toHaveClass(/help-sidebar-open/);
    await expect(page.locator("body")).toHaveClass(/sidebar-collapsed/);
    await expect(page.locator("#help-drawer")).not.toHaveClass(/translate-x-full/);
    const runs = await page.evaluate(
        () => (window as unknown as { __layoutTransitions: string[] }).__layoutTransitions,
    );
    expect(runs).toEqual([]);
});

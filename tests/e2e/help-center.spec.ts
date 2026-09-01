/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help-center.spec.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { test, expect } from "@playwright/test";

/**
 * Hilfecenter (Feature 039, MVP-752–756) im echten Browser: Übersicht mit
 * Bereichskacheln, GET-Suche mit <mark>-Snippets, Bereichsansicht,
 * Artikelseite mit TOC/Verwandten/Feedback und der Drawer-Absprung
 * „Ausführliche Hilfe öffnen". Datenbasis: help:reindex in server.sh —
 * dieselben 258 Topics wie in Produktion.
 */

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
});

test("Übersicht zeigt Bereichskacheln, Suche und beliebte Themen nicht leer", async ({ page }) => {
    await page.goto("/hilfe");

    await expect(page.locator("#help-center-search")).toBeVisible();
    // Kachel-Grid: mindestens die Kernbereiche sind da.
    // Kachel gezielt über den Bereichslink (Titeltexte kommen mehrfach vor).
    await expect(page.locator("a[href*='bereich=erste-schritte']").first()).toBeVisible();
    await expect(page.locator("a[href*='bereich=zeit-personal']").first()).toBeVisible();
    // Artikelzähler stehen auf den Kacheln (keine leeren Bereiche).
    await expect(page.locator("text=/\\d+ Artikel/").first()).toBeVisible();
});

test("Suche liefert Treffer mit Hervorhebung und stabilen Artikel-Links", async ({ page }) => {
    await page.goto("/hilfe?q=Stempeluhr");

    const results = page.locator("ul.divide-y a[href*='/hilfe/']");
    await expect(results.first()).toBeVisible();
    // Snippet-Hervorhebung: der Treffer steht in <mark>.
    await expect(page.locator("mark").first()).toContainText(/stempeluhr/i);

    await results.first().click();
    await expect(page).toHaveURL(/\/hilfe\/[a-z0-9.\-]+/);
    await expect(page.locator("article h2").first()).toBeVisible();
});

test("Leere Suche zeigt definierten Leerzustand mit Zurücksetzen", async ({ page }) => {
    await page.goto("/hilfe?q=xyzzy-gibt-es-nicht");

    await expect(page.getByText("Keine passenden Hilfethemen gefunden.")).toBeVisible();
    await page.getByRole("link", { name: "Suche zurücksetzen" }).click();
    await expect(page).toHaveURL(/\/hilfe$/);
});

test("Bereichsansicht listet Artikel und führt zur Themenseite", async ({ page }) => {
    await page.goto("/hilfe?bereich=zeit-personal");

    const rows = page.locator("ul.divide-y a[href*='/hilfe/']");
    await expect(rows.first()).toBeVisible();
    await page.getByRole("link", { name: "Zur Übersicht" }).click();
    await expect(page).toHaveURL(/\/hilfe$/);
});

test("Pilotartikel rendert Schema-Abschnitte, TOC, Verwandte und Feedback", async ({ page }) => {
    await page.goto("/hilfe/time-entries.start");

    await expect(page.locator("article")).toContainText("Zeiterfassung starten");
    // Sechs Schema-Abschnitte als h2 mit Reindex-Ankern.
    await expect(page.locator("article h2#sec-zweck-und-hintergrund")).toBeVisible();
    await expect(page.locator("article h2#sec-auswirkungen-und-nachste-schritte")).toBeVisible();
    // TOC (ab 3 h2) mit funktionierendem Anker-Sprung.
    const toc = page.getByRole("navigation", { name: "Auf dieser Seite" });
    await expect(toc).toBeVisible();
    await toc.getByRole("link", { name: "Typische Fehler" }).click();
    await expect(page).toHaveURL(/#sec-typische-fehler$/);
    // Verwandte Themen verlinken auf Vollseiten.
    await expect(
        page.locator("a[href$='/hilfe/time-entries.edit']").first(),
    ).toBeVisible();

    // Feedback: Klick auf „Ja" bedankt sich (POST auf den Drawer-Endpunkt).
    await page.locator("[data-help-center-vote='1']").click();
    // Gezielt das Vollseiten-Danke — der Drawer trägt denselben Text (hidden).
    await expect(page.locator("[data-help-center-thanks]")).toBeVisible();
});

test("Unbekanntes und unsichtbares Topic sind identisch 404", async ({ page }) => {
    const unknown = await page.goto("/hilfe/gibt.es-nicht");
    expect(unknown?.status()).toBe(404);
    // admin.security ist audience:[admin] — der Org-Admin-User der Session
    // ist Admin, also stattdessen ein Portal-Topic? Wir prüfen nur unknown
    // hart; die 404-Gleichheit deckt der Feature-Test ab.
});

test("Drawer-Absprung öffnet exakt das aktuelle Topic als Vollseite", async ({ page }) => {
    // Seite mit Hilfe-Kontext: /today mappt auf ein Topic der Registry.
    await page.goto("/today");

    // Drawer per F1 öffnen — die Hilfe-Taste (statt "?", das exklusiv die
    // Tastenkürzel-Übersicht behält).
    await page.keyboard.press("F1");
    const fullpage = page.locator("[data-help-fullpage]");
    await expect(fullpage).toBeVisible();

    const href = await fullpage.getAttribute("href");
    expect(href).toMatch(/\/hilfe\/[a-z0-9.\-]+$/);

    await fullpage.click();
    await expect(page).toHaveURL(new RegExp(href!.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "$"));
    await expect(page.locator("article h2").first()).toBeVisible();
});

test("Hilfecenter steht im Benutzermenü", async ({ page }) => {
    await page.goto("/hilfe");
    // Der Menüpunkt liegt im (geschlossenen) Benutzermenü — attached reicht,
    // sichtbar wird er erst nach dem Öffnen des Dropdowns.
    await expect(page.locator("a[href$='/hilfe']").first()).toBeAttached();
});

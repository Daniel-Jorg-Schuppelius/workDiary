/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : helpers.ts
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { expect, type Page, type Locator } from "@playwright/test";

export const CLOCK_IN = 'form[data-offline-sync="attendance.clock-in"]';
export const CLOCK_OUT = 'form[data-offline-sync="attendance.clock-out"]';

/**
 * Die Stempel-Formulare existieren auf /today mehrfach (Panel + kompakte
 * Variante) — für Klicks zählt nur die sichtbare Ausprägung.
 */
export function clockInSubmit(page: Page): Locator {
    return page
        .locator(`${CLOCK_IN} button[type="submit"]`)
        .filter({ visible: true })
        .first();
}

export function clockOutSubmit(page: Page): Locator {
    return page
        .locator(`${CLOCK_OUT} button[type="submit"]`)
        .filter({ visible: true })
        .first();
}

/** Offene Stempelung aus früheren Läufen schließen (idempotenter Start). */
export async function ensureClockedOut(page: Page) {
    if (await page.locator(CLOCK_OUT).count()) {
        await clockOutSubmit(page).click();
        await expect(clockInSubmit(page)).toBeVisible();
    }
}

/**
 * Status-Codes aus App\Enums\Diary\Status — die Kanban-Spalten tragen sie als
 * data-status. Bewusst als Konstanten statt magischer Zahlen im Spec.
 */
export const STATUS = {
    COMPLETED: -1,
    IN_PROGRESS: 1,
    PLANNED: 2,
    WAITING_CUSTOMER: 3,
    ACCEPTED: 4,
    WAITING_MATERIAL: 5,
    ACCEPTED_FINAL: 6,
    INVOICED: 7,
    CANCELLED: 8,
} as const;

/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : toolbar-overflow.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Pure Logik des Seitenkopfs mit Überlaufmenü (MVP-966) — ohne DOM, damit sie
 * unter node:test prüfbar ist. Die Verdrahtung liegt in
 * resources/js/toolbar-overflow.js.
 *
 * Platzierungen: `auto` wandert bei Platzmangel ins Menü, `bar` bleibt immer
 * stehen, `menu` und `danger` stehen immer im Menü (`danger` im abgesetzten
 * Schlussteil).
 */

/** @typedef {"auto" | "bar" | "menu" | "danger"} Placement */
/** @typedef {{ width: number, placement: Placement }} OverflowItem */

export const PLACEMENTS = ["auto", "bar", "menu", "danger"];

/** Der Titelblock behält min(14rem, 45 %) — dieselbe Formel steht als Klasse im Blade. */
export const HEAD_MIN_REM = 14;
export const HEAD_MIN_RATIO = 0.45;

/**
 * @param {unknown} value
 * @returns {Placement}
 */
export function normalizePlacement(value) {
    return PLACEMENTS.includes(/** @type {string} */ (value)) ? /** @type {Placement} */ (value) : "auto";
}

/**
 * @param {number} innerWidth Inhaltsbreite der Toolbar
 * @param {number} rootFontSize
 */
export function headReserve(innerWidth, rootFontSize) {
    return Math.min(HEAD_MIN_REM * rootFontSize, HEAD_MIN_RATIO * innerWidth);
}

/**
 * Verteilt die Aktionen auf Leiste und Menü. Unsichtbare Aktionen (Breite 0)
 * bleiben unberührt in der Leiste.
 *
 * @param {OverflowItem[]} items in Quelltext-Reihenfolge
 * @param {number} available Platz für Aktionen samt ⋯-Knopf
 * @param {number} moreWidth Breite des ⋯-Knopfs
 * @param {number} gap Abstand zwischen zwei Aktionen
 * @returns {{ menu: number[], compact: boolean }} Menü-Indizes aufsteigend;
 *          `compact`, wenn selbst die festen Aktionen nicht passen
 */
export function planOverflow(items, available, moreWidth, gap) {
    const inMenu = new Set();
    items.forEach((item, index) => {
        if (item.width > 0 && (item.placement === "menu" || item.placement === "danger")) {
            inMenu.add(index);
        }
    });

    const needed = () => {
        let total = 0;
        let count = 0;
        items.forEach((item, index) => {
            if (item.width > 0 && !inMenu.has(index)) {
                total += item.width;
                count++;
            }
        });
        if (inMenu.size > 0) {
            total += moreWidth;
            count++;
        }
        return total + gap * Math.max(0, count - 1);
    };

    for (let index = items.length - 1; index >= 0 && needed() > available; index--) {
        const item = items[index];
        if (item.placement === "auto" && item.width > 0) {
            inMenu.add(index);
        }
    }

    return {
        menu: [...inMenu].sort((a, b) => a - b),
        compact: needed() > available,
    };
}

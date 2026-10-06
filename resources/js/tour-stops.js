/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : tour-stops.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Stopps einer Tour per Ziehen sortieren (tours/edit.blade.php) über
 * lib/pointer-sort.js: Maus an der ganzen Zeile, Finger und Stift am Griff.
 * Die Reihenfolge steckt in der Position der Zeilen (order_ids[]) und wird
 * erst mit dem Formular gespeichert.
 */

import { pointerSort } from "./lib/pointer-sort.js";

function init() {
    const list = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-stop-list]")
    );
    if (!list) return;

    pointerSort(list, {
        item: "[data-stop-item]",
        handle: "[data-stop-handle]",
        mouseAnywhere: true,
        mode: "live",
        draggingClass: ["opacity-50"],
        onDrop: () => {
            list.querySelectorAll("[data-stop-item]").forEach((item, index) => {
                const badge = item.querySelector("[data-stop-pos]");
                if (badge) badge.textContent = String(index + 1);
            });
        },
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

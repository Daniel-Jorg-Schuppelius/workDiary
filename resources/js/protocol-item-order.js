/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : protocol-item-order.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Protokollpunkte per Ziehen sortieren (protocols/_reorder_dialog.blade.php,
 * MVP-1573) über lib/pointer-sort.js: Maus an der ganzen Zeile, Finger und
 * Stift am Griff. Der Dialog wird nachgeladen, deshalb hängt die Geste am
 * Dokument; die Reihenfolge steckt in der Position der Zeilen (order_ids[])
 * und wird erst mit dem Formular gespeichert.
 */

import { pointerSort } from "./lib/pointer-sort.js";

pointerSort(document, {
    item: "[data-protocol-order-item]",
    list: "[data-protocol-order-list]",
    handle: "[data-protocol-order-handle]",
    mouseAnywhere: true,
    mode: "live",
    draggingClass: ["opacity-50"],
});

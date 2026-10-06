/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : agile-backlog.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { submitForm } from "./lib/http.js";
import { pointerSort, predecessorIndex } from "./lib/pointer-sort.js";

/**
 * Umsortieren des Agile-Backlogs per Ziehen (Audit 2026-08, W4.2) über
 * lib/pointer-sort.js: mit der Maus an der ganzen Zeile, mit Finger und Stift
 * am Griff in der Rang-Spalte.
 *
 * Der Server-Endpunkt (`agile.items.rerank`) nimmt den Sqid des neuen
 * Vorgaengers (`after`, leer = Spitze) und die `lock_version` fuer
 * optimistisches Sperren. Die Hoch-/Runter-Buttons der Zeile bedienen
 * denselben Endpunkt und bleiben der Tastatur-/A11y-Pfad.
 *
 * Die Zeile wird beim Ablegen NICHT lokal umsortiert: der Server ist die
 * Wahrheit (Rang, Sperrversion, Blockierungen), das Formular-POST laedt die
 * Seite ohnehin neu. Das vermeidet ein Auseinanderlaufen von Anzeige und
 * Datenstand bei abgelehnten Zuegen.
 */

function init() {
    const body = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-backlog-rows]")
    );
    if (!body) return;

    pointerSort(body, {
        item: "[data-backlog-row]",
        handle: "[data-backlog-handle]",
        mouseAnywhere: true,
        // Nach oben gezogen landet die Zeile VOR der Zielzeile, nach unten
        // gezogen dahinter — die Marke umrahmt die ganze Zielzeile.
        side: "direction",
        canDrag: (row) => row.dataset.canPrioritize === "1",
        draggingClass: ["opacity-50"],
        targetClass: ["outline", "outline-primary"],
        onDrop: ({ item, list, from, to }) => {
            const url = item.dataset.rerankUrl;
            if (!url) return;
            const rows = /** @type {NodeListOf<HTMLElement>} */ (
                list.querySelectorAll("[data-backlog-row]")
            );
            submitForm(
                url,
                {
                    after: rows[predecessorIndex(from, to)]?.dataset.sqid || "",
                    lock_version: item.dataset.lockVersion || "",
                },
                "PATCH",
            );
        },
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

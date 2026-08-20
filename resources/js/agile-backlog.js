/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : agile-backlog.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Drag-&-Drop-Umsortierung des Agile-Backlogs (Audit 2026-08, W4.2).
 *
 * Der Server-Endpunkt (`agile.items.rerank`) war seit MVP-Bau vorbereitet —
 * er nimmt den Sqid des neuen Vorgaengers (`after`, leer = Spitze) und die
 * `lock_version` fuer optimistisches Sperren. Bisher bedienten ihn nur die
 * Hoch-/Runter-Buttons; die bleiben als Tastatur-/A11y-Pfad erhalten.
 *
 * Die Zeile wird beim Drop NICHT lokal umsortiert: der Server ist die
 * Wahrheit (Rang, Sperrversion, Blockierungen), das Formular-POST laedt die
 * Seite ohnehin neu. Das vermeidet ein Auseinanderlaufen von Anzeige und
 * Datenstand bei abgelehnten Zuegen.
 */

/** @param {string} url @param {Record<string,string>} fields */
function submitRerank(url, fields) {
    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? "";
    const form = document.createElement("form");
    form.method = "POST";
    form.action = url;
    form.hidden = true;

    const add = (name, value) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };
    add("_token", csrf);
    add("_method", "PATCH");
    Object.entries(fields || {}).forEach(([name, value]) => add(name, value));

    document.body.appendChild(form);
    form.submit();
}

function init() {
    const body = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-backlog-rows]")
    );
    if (!body) return;

    /** @type {HTMLElement | null} */
    let dragRow = null;

    const clearMarkers = () => {
        body
            .querySelectorAll("[data-backlog-row]")
            .forEach((row) => row.classList.remove("outline", "outline-primary"));
    };

    body.addEventListener("dragstart", (event) => {
        const row = /** @type {HTMLElement | null} */ (
            event.target instanceof Element
                ? event.target.closest("[data-backlog-row]")
                : null
        );
        if (!row || row.dataset.canPrioritize !== "1") return;
        dragRow = row;
        row.classList.add("opacity-50");
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = "move";
            try {
                event.dataTransfer.setData("text/plain", row.dataset.sqid || "");
            } catch (_e) {
                /* ältere Engines */
            }
        }
    });

    body.addEventListener("dragend", () => {
        dragRow?.classList.remove("opacity-50");
        dragRow = null;
        clearMarkers();
    });

    body.addEventListener("dragover", (event) => {
        if (!dragRow) return;
        const row = /** @type {HTMLElement | null} */ (
            event.target instanceof Element
                ? event.target.closest("[data-backlog-row]")
                : null
        );
        if (!row || row === dragRow) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = "move";
        clearMarkers();
        row.classList.add("outline", "outline-primary");
    });

    body.addEventListener("drop", (event) => {
        event.preventDefault();
        const target = /** @type {HTMLElement | null} */ (
            event.target instanceof Element
                ? event.target.closest("[data-backlog-row]")
                : null
        );
        const row = dragRow;
        dragRow = null;
        clearMarkers();
        row?.classList.remove("opacity-50");
        if (!row || !target || row === target) return;

        // Ziel-Position: wird die Zeile nach oben gezogen, landet sie VOR der
        // Zielzeile (Vorgaenger = deren Vorgaenger); nach unten gezogen, landet
        // sie hinter der Zielzeile.
        const rows = Array.from(body.querySelectorAll("[data-backlog-row]"));
        const fromIndex = rows.indexOf(row);
        const toIndex = rows.indexOf(target);
        const after =
            toIndex < fromIndex
                ? /** @type {HTMLElement | undefined} */ (rows[toIndex - 1])
                      ?.dataset.sqid || ""
                : target.dataset.sqid || "";

        const url = row.dataset.rerankUrl;
        if (!url) return;
        submitRerank(url, {
            after,
            lock_version: row.dataset.lockVersion || "",
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

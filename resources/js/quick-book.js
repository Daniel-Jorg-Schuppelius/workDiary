// Quick-Buchung offener Zeitblöcke (MVP-015, Rang 37).
//
// Progressive Enhancement über den No-JS-Fallback: jedes Block-Formular
// (`.qb-form`) bleibt ohne JS ganz normal absendbar (Server-Redirect). Mit JS
// kommen hinzu: (a) einen Block auf ein Projekt-Ziel ziehen (lib/pointer-sort.js:
// Maus an der ganzen Zeile, Finger und Stift am Griff), (b) Ctrl/Cmd+Enter im
// Fokus-Formular = buchen + weiter. Beide Wege posten JSON an denselben
// Endpunkt und laden danach die Seite neu (nächster offener Block wird
// automatisch der erste).

import { postJson } from "./lib/http.js";
import { pointerSort } from "./lib/pointer-sort.js";

/**
 * @param {string} url
 * @param {Record<string, unknown>} payload
 */
async function postBooking(url, payload) {
    return (await postJson(url, payload)).ok;
}

/** @param {HTMLFormElement} form */
function payloadFromForm(form) {
    const data = new FormData(form);
    /** @type {Record<string, FormDataEntryValue>} */
    const payload = {};
    for (const [key, value] of data.entries()) {
        if (key !== "_token" && value !== "") payload[key] = value;
    }
    return payload;
}

export function bindQuickBook() {
    const panel = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-qb-panel]")
    );
    if (!panel) return;
    const url = panel.getAttribute("data-qb-url");
    if (!url) return;

    // (a) Fallback-Formular je Block: submit abfangen → JSON posten → reload.
    panel.addEventListener("submit", async (event) => {
        const form = /** @type {HTMLFormElement | null} */ (
            /** @type {HTMLElement} */ (event.target).closest(".qb-form")
        );
        if (!form) return;
        // Ungültiges Formular (kein Projekt) → native Validierung greifen lassen.
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        if (await postBooking(url, payloadFromForm(form))) {
            window.location.reload();
        } else {
            form.submit(); // harter Fallback ohne Enhancement
        }
    });

    // (b) Ctrl/Cmd+Enter im Block-Formular = buchen + weiter.
    panel.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" || !(event.ctrlKey || event.metaKey)) return;
        const form = /** @type {HTMLFormElement | null} */ (
            /** @type {HTMLElement} */ (event.target).closest(".qb-form")
        );
        if (!form) return;
        event.preventDefault();
        form.requestSubmit();
    });

    // (c) Block auf ein Projekt-Ziel ziehen. Die Formularfelder der Zeile
    // behalten ihre Zeigergesten (Standard-Ausnahmen des Bausteins).
    pointerSort(panel, {
        item: "[data-qb-block]",
        handle: "[data-qb-handle]",
        mouseAnywhere: true,
        target: "[data-qb-target]",
        axis: "both",
        draggingClass: ["opacity-50"],
        targetClass: ["qb-target-over"],
        onDrop: async ({ item, target }) => {
            if (!target) return;
            const startedAt = item.getAttribute("data-started-at");
            const endedAt = item.getAttribute("data-ended-at");
            if (!startedAt || !endedAt) return;

            const ok = await postBooking(url, {
                project: target.getAttribute("data-project"),
                started_at: startedAt,
                ended_at: endedAt,
            });
            if (ok) window.location.reload();
        },
    });
}

document.addEventListener("DOMContentLoaded", () => bindQuickBook());

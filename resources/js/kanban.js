/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * kanban.js — workflow-konformes Drag-and-Drop auf dem Kanban-Board.
 *
 * Ein Spaltenzug wird auf die passende fachliche Auftragsaktion
 * (POST diary/{diary}/lifecycle/{action}) abgebildet — nie auf einen
 * direkten Status-Schreibzugriff. Aktionen mit Pflichtangaben öffnen den
 * zugehörigen Dialog aus kanban/_action_dialogs.blade.php; unzulässige Züge
 * werden mit Hinweis abgewiesen. Welche Aktionen der Nutzer je Karte darf,
 * liefert das Backend als data-actions (Aktion → URL); der Server prüft
 * Übergang und Berechtigung beim POST erneut.
 */

import { __ } from "./i18n.js";
import { submitForm } from "./lib/http.js";

// from-Status → to-Status → Aktion (Status-Codes aus App\Enums\Diary\Status).
// dialog: Dialog-ID für Pflichtangaben; fields: feste Zusatzfelder;
// blockedMessage: Zug existiert fachlich, ist aber bewusst nicht per Drag.
const TRANSITIONS = {
    2: {
        4: { action: "accept" },
        8: { action: "cancel", dialog: "kanban-cancel-dialog" },
    },
    4: {
        1: { action: "start" },
        8: { action: "cancel", dialog: "kanban-cancel-dialog" },
    },
    1: {
        3: { action: "pause", fields: { reason: "customer" } },
        5: { action: "pause", fields: { reason: "material" } },
        "-1": { action: "complete", dialog: "kanban-complete-dialog" },
        8: { action: "cancel", dialog: "kanban-cancel-dialog" },
    },
    3: { 1: { action: "resume" } },
    5: { 1: { action: "resume" } },
    "-1": { 6: { blockedMessage: "js.kanban.handover_via_order" } },
    6: { 7: { action: "markInvoiced", dialog: "kanban-invoice-dialog" } },
};

function notify(tone, message) {
    if (typeof window.notifyAction === "function") {
        window.notifyAction({ tone, message });
    } else {
        // browser-dialog-ok: Fallback, falls die Notify-Infrastruktur nicht geladen ist.
        window.alert(message);
    }
}

// Klassischer Form-POST statt fetch: die Lifecycle-Antwort ist ein Redirect
// mit Flash-Meldung, die Seite lädt danach ohnehin neu.
function submitLifecycle(url, fields) {
    submitForm(url, fields || {});
}

function openActionDialog(dialogId, url) {
    const dlg = /** @type {HTMLDialogElement | null} */ (
        document.getElementById(dialogId)
    );
    const form = dlg?.querySelector("form");
    if (!dlg || !form || typeof dlg.showModal !== "function") return false;
    form.action = url;
    form.reset();
    dlg.showModal();
    return true;
}

function handleDrop(card, column) {
    const from = card.dataset.status ?? "";
    const to = column.dataset.status ?? "";
    if (from === "" || to === "" || from === to) return;

    const spec = (TRANSITIONS[from] || {})[to];
    if (!spec) {
        notify("warning", __("js.kanban.invalid_move"));
        return;
    }
    if (spec.blockedMessage) {
        notify("info", __(spec.blockedMessage));
        return;
    }

    let actions = {};
    try {
        actions = JSON.parse(card.dataset.actions || "{}");
    } catch (_e) {
        actions = {};
    }
    const url = actions[spec.action];
    if (!url) {
        notify("warning", __("js.kanban.not_allowed"));
        return;
    }

    if (spec.dialog) {
        openActionDialog(spec.dialog, url);
        return;
    }
    submitLifecycle(url, spec.fields);
}

function init() {
    const board = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-kanban-board]")
    );
    if (!board) return;

    /** @type {HTMLElement | null} */
    let dragCard = null;

    const clearHighlights = () => {
        board
            .querySelectorAll("[data-kanban-column]")
            .forEach((col) => col.classList.remove("ring-2", "ring-primary"));
    };

    board.addEventListener("dragstart", (event) => {
        const card = /** @type {HTMLElement | null} */ (
            event.target instanceof Element
                ? event.target.closest("[data-kanban-card]")
                : null
        );
        if (!card) return;
        dragCard = card;
        card.classList.add("opacity-50");
        event.dataTransfer.effectAllowed = "move";
        try {
            event.dataTransfer.setData("text/plain", card.dataset.id || "");
        } catch (_e) {
            /* IE/ältere Engines */
        }
    });

    board.addEventListener("dragend", () => {
        dragCard?.classList.remove("opacity-50");
        dragCard = null;
        clearHighlights();
    });

    board.querySelectorAll("[data-kanban-column]").forEach((col) => {
        const column = /** @type {HTMLElement} */ (col);
        column.addEventListener("dragover", (event) => {
            if (!dragCard) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = "move";
            clearHighlights();
            column.classList.add("ring-2", "ring-primary");
        });
        column.addEventListener("dragleave", () => {
            column.classList.remove("ring-2", "ring-primary");
        });
        column.addEventListener("drop", (event) => {
            event.preventDefault();
            clearHighlights();
            const card = dragCard;
            dragCard = null;
            if (!card) return;
            card.classList.remove("opacity-50");
            handleDrop(card, column);
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

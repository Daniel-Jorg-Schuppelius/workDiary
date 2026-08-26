/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * kanban.js — workflow-konformes Verschieben auf dem Kanban-Board.
 *
 * Ein Spaltenzug wird auf die passende fachliche Auftragsaktion
 * (POST diary/{diary}/lifecycle/{action}) abgebildet — nie auf einen
 * direkten Status-Schreibzugriff. Aktionen mit Pflichtangaben öffnen den
 * zugehörigen Dialog aus kanban/_action_dialogs.blade.php; unzulässige Züge
 * werden mit Hinweis abgewiesen. Welche Aktionen der Nutzer je Karte darf,
 * liefert das Backend als data-actions (Aktion → URL); der Server prüft
 * Übergang und Berechtigung beim POST erneut.
 *
 * Zwei gleichwertige Eingabewege (MVP-725, Vollscan 2026-08-23 D4):
 *
 *  1. **Zeigergeste** über Pointer Events statt HTML5-Drag-and-drop. HTML5-DnD
 *     kennt weder Touch noch Stift (mobile Browser lösen dragstart nicht aus)
 *     und ist synthetisch kaum testbar. Pointer Events decken Maus, Touch und
 *     Stift mit einem Codepfad ab. Die Karte trägt `touch-pan-y`: vertikales
 *     Scrollen der Spalte bleibt Sache des Browsers, der Zug beginnt erst nach
 *     einer Bewegung über der Schwelle (sonst wäre jeder Tap ein Zug).
 *  2. **Tastatur/Barrierefreiheit**: Karte fokussieren, `m` oder Leertaste →
 *     „Verschieben nach"-Menü mit genau den fachlich erlaubten Zielspalten;
 *     Auswahl per Tab/Pfeiltasten, Enter bestätigt, Esc bricht ab. Das Menü
 *     ist zugleich der Touch-Weg über den Karten-Button (data-kanban-move).
 */

import { __ } from "./i18n.js";
import { submitForm } from "./lib/http.js";

// from-Status → to-Status → Aktion (Status-Codes aus App\Enums\Diary\Status).
// dialog: Dialog-ID für Pflichtangaben; fields: feste Zusatzfelder;
// blockedMessage: Zug existiert fachlich, ist aber bewusst nicht per Geste.
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

// Ab dieser Bewegung (px) gilt eine Zeigergeste als Zug und nicht mehr als
// Klick auf die Karte (die Karte ist ein Link auf den Eintrag).
const DRAG_THRESHOLD = 6;

const MOVE_DIALOG_ID = "kanban-move-dialog";

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

/** @returns {Record<string, string>} Aktion → Lifecycle-URL (Gate-gefiltert vom Server). */
function cardActions(card) {
    try {
        const parsed = JSON.parse(card.dataset.actions || "{}");
        return parsed && typeof parsed === "object" ? parsed : {};
    } catch (_e) {
        return {};
    }
}

/**
 * Prüft einen Zug rein fachlich (ohne ihn auszuführen).
 *
 * @returns {{spec: any, url: string} | {error: string, tone: string} | null}
 *          null = kein Zug (gleiche Spalte / unvollständige Daten)
 */
function planMove(card, toStatus) {
    const from = card.dataset.status ?? "";
    if (from === "" || toStatus === "" || from === toStatus) return null;

    const spec = (TRANSITIONS[from] || {})[toStatus];
    if (!spec) return { error: "js.kanban.invalid_move", tone: "warning" };
    if (spec.blockedMessage) return { error: spec.blockedMessage, tone: "info" };

    const url = cardActions(card)[spec.action];
    if (!url) return { error: "js.kanban.not_allowed", tone: "warning" };

    return { spec, url };
}

/** Führt den Zug aus bzw. meldet, warum er nicht geht. */
function applyMove(card, toStatus) {
    const plan = planMove(card, toStatus);
    if (!plan) return;
    if ("error" in plan) {
        notify(plan.tone, __(plan.error));
        return;
    }
    if (plan.spec.dialog) {
        openActionDialog(plan.spec.dialog, plan.url);
        return;
    }
    submitLifecycle(plan.url, plan.spec.fields);
}

/**
 * Zielspalten, in die diese Karte fachlich UND berechtigungsseitig darf —
 * Grundlage des „Verschieben nach"-Menüs.
 *
 * @returns {Array<{status: string, label: string}>}
 */
function availableTargets(board, card) {
    /** @type {Array<{status: string, label: string}>} */
    const targets = [];
    board.querySelectorAll("[data-kanban-column]").forEach((col) => {
        const column = /** @type {HTMLElement} */ (col);
        const status = column.dataset.status ?? "";
        const plan = planMove(card, status);
        if (!plan || "error" in plan) return;
        targets.push({ status, label: column.dataset.label || status });
    });
    return targets;
}

function openMoveMenu(board, card) {
    const dlg = /** @type {HTMLDialogElement | null} */ (
        document.getElementById(MOVE_DIALOG_ID)
    );
    const list = dlg?.querySelector("[data-kanban-move-options]");
    if (!dlg || !list || typeof dlg.showModal !== "function") return;

    // Optionen neu aufbauen — bewusst über createElement/textContent statt
    // innerHTML (SafeHtml-Grenze, resources/js/lib/html.js).
    while (list.firstChild) list.removeChild(list.firstChild);

    const targets = availableTargets(board, card);
    if (targets.length === 0) {
        const empty = document.createElement("p");
        empty.className = "text-sm text-muted";
        empty.textContent = __("js.kanban.no_targets");
        list.appendChild(empty);
    } else {
        targets.forEach((target) => {
            const option = document.createElement("button");
            option.type = "button";
            option.className = "btn btn-sm btn-outline w-full justify-start";
            option.dataset.kanbanMoveTarget = target.status;
            option.textContent = target.label;
            option.addEventListener("click", () => {
                dlg.close();
                applyMove(card, target.status);
            });
            list.appendChild(option);
        });
    }

    dlg.showModal();
    /** @type {HTMLElement | null} */ (list.querySelector("button"))?.focus();
}

function init() {
    const board = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-kanban-board]")
    );
    if (!board) return;

    const columns = () => board.querySelectorAll("[data-kanban-column]");

    const clearHighlights = () => {
        columns().forEach((col) => col.classList.remove("ring-2", "ring-primary"));
    };

    /** Spalte unter dem Zeiger (die Karte selbst liegt darunter → closest). */
    const columnAt = (x, y) => {
        const element = document.elementFromPoint(x, y);
        return element instanceof Element
            ? /** @type {HTMLElement | null} */ (
                  element.closest("[data-kanban-column]")
              )
            : null;
    };

    /** @type {{card: HTMLElement, pointerId: number, startX: number, startY: number, dragging: boolean} | null} */
    let drag = null;
    // Ein Zug endet auf der Karte — der folgende Klick würde sonst den
    // Eintrags-Dialog öffnen.
    let suppressClick = false;

    const endDrag = () => {
        if (!drag) return null;
        const { card, dragging } = drag;
        card.classList.remove("opacity-50");
        board.classList.remove("wd-kanban-dragging");
        clearHighlights();
        drag = null;
        return dragging ? card : null;
    };

    board.addEventListener("pointerdown", (event) => {
        if (event.button !== 0 && event.pointerType === "mouse") return;
        const target = event.target instanceof Element ? event.target : null;
        if (!target || target.closest("[data-kanban-move]")) return;
        const card = /** @type {HTMLElement | null} */ (
            target.closest("[data-kanban-card]")
        );
        if (!card) return;

        drag = {
            card,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            dragging: false,
        };
    });

    board.addEventListener("pointermove", (event) => {
        if (!drag || event.pointerId !== drag.pointerId) return;

        if (!drag.dragging) {
            const dx = event.clientX - drag.startX;
            const dy = event.clientY - drag.startY;
            if (Math.hypot(dx, dy) < DRAG_THRESHOLD) return;
            drag.dragging = true;
            drag.card.classList.add("opacity-50");
            board.classList.add("wd-kanban-dragging");
            // Pointer-Capture: Bewegungen über Spaltengrenzen hinweg kommen
            // weiterhin bei der Karte an (und bubbeln zum Board).
            try {
                drag.card.setPointerCapture(event.pointerId);
            } catch (_e) {
                /* ältere Engines ohne Capture */
            }
        }

        event.preventDefault();
        clearHighlights();
        columnAt(event.clientX, event.clientY)?.classList.add(
            "ring-2",
            "ring-primary",
        );
    });

    board.addEventListener("pointerup", (event) => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        const column = columnAt(event.clientX, event.clientY);
        const card = endDrag();
        if (!card) return;
        // Der Browser feuert nach dem Zug noch einen Klick (die Karte ist ein
        // Link auf den Eintrag) — genau diesen einen verschlucken. Endet der Zug
        // über einer anderen Spalte, bleibt der Klick manchmal aus; das
        // setTimeout verhindert, dass die Sperre auf den NÄCHSTEN Klick fällt.
        suppressClick = true;
        window.setTimeout(() => {
            suppressClick = false;
        }, 0);
        if (column) applyMove(card, column.dataset.status ?? "");
    });

    board.addEventListener("pointercancel", (event) => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        endDrag();
    });

    // Capture-Phase: der Klick darf den Eintrags-Dialog gar nicht erst erreichen.
    board.addEventListener(
        "click",
        (event) => {
            if (!suppressClick) return;
            suppressClick = false;
            event.preventDefault();
            event.stopPropagation();
        },
        true,
    );

    // Touch-/Maus-Weg ins Menü (Karten-Button) …
    board.addEventListener("click", (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const trigger = target?.closest("[data-kanban-move]");
        if (!trigger) return;
        const card = /** @type {HTMLElement | null} */ (
            trigger.closest("[data-kanban-card]")
        );
        if (!card) return;
        event.preventDefault();
        openMoveMenu(board, card);
    });

    // … und der Tastaturweg auf der fokussierten Karte.
    board.addEventListener("keydown", (event) => {
        const target = event.target instanceof Element ? event.target : null;
        // Auf dem Menü-Button erledigt der Klick-Weg die Leertaste bereits.
        if (!target || target.closest("[data-kanban-move]")) return;
        const card = /** @type {HTMLElement | null} */ (
            target.closest("[data-kanban-card]")
        );
        if (!card) return;
        if (event.key !== "m" && event.key !== "M" && event.key !== " ") return;
        // Leertaste aktiviert Links nicht — das Kürzel kollidiert also nicht
        // mit dem Öffnen des Eintrags (Enter).
        event.preventDefault();
        openMoveMenu(board, card);
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

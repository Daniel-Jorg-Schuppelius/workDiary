/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanban-moves.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Fachliche Entscheidung eines Spaltenzugs auf dem Kanban-Board (ohne DOM):
 * welche Auftragsaktion ein Zug auslöst bzw. mit welchem Hinweis er
 * abgewiesen wird. resources/js/kanban.js führt die Entscheidung aus; der
 * Server prüft Übergang und Berechtigung beim POST erneut.
 */

// from-Status → to-Status → Aktion (Status-Codes aus App\Enums\Diary\Status).
// dialog: Dialog-ID für Pflichtangaben; fields: feste Zusatzfelder;
// blockedMessage: Zug existiert fachlich, ist aber bewusst nicht per Geste.
/**
 * @typedef {object} TransitionSpec
 * @property {string} [action]
 * @property {string} [dialog]
 * @property {Record<string, string>} [fields]
 * @property {string} [blockedMessage]
 */
/** @type {Record<string, Record<string, TransitionSpec>>} */
export const TRANSITIONS = {
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

/**
 * Prüfspalten (Feature 198): Zur Prüfung, Rückfrage, Freigegeben. Vorlegen,
 * Freigeben und Zurückfragen brauchen Angaben und Vier-Augen-Prüfung und
 * laufen deshalb im Auftrag, nie per Zug.
 */
export const REVIEW_STATUSES = ["9", "10", "11"];

const REVIEW_MESSAGE = "js.kanban.review_via_order";

/**
 * @typedef {object} PlannedMove
 * @property {TransitionSpec} spec
 * @property {string} url
 */
/**
 * @typedef {object} RejectedMove
 * @property {string} error Übersetzungsschlüssel
 * @property {string} tone
 */

/**
 * Prüft einen Zug rein fachlich (ohne ihn auszuführen).
 *
 * @param {string} from Status der Karte
 * @param {string} to Status der Zielspalte
 * @param {Record<string, string>} actions Aktion → Lifecycle-URL (Gate-gefiltert vom Server)
 * @param {boolean} [requiresReview] Auftrag mit Prüfpflicht: abgeschlossen wird erst nach der Freigabe
 * @returns {PlannedMove | RejectedMove | null} null = kein Zug (gleiche Spalte / unvollständige Daten)
 */
export function planMove(from, to, actions, requiresReview = false) {
    if (from === "" || to === "" || from === to) return null;

    const spec = (TRANSITIONS[from] || {})[to];
    if (!spec) {
        // In, aus und zwischen den Prüfspalten: verständlich auf den Auftrag verweisen.
        if (REVIEW_STATUSES.includes(from) || REVIEW_STATUSES.includes(to)) {
            return { error: REVIEW_MESSAGE, tone: "info" };
        }
        return { error: "js.kanban.invalid_move", tone: "warning" };
    }
    if (spec.blockedMessage) return { error: spec.blockedMessage, tone: "info" };
    const action = /** @type {string} */ (spec.action);
    // Mit Prüfpflicht führt „In Bearbeitung“ über die Vorlage, nicht direkt zu „Erledigt“.
    if (action === "complete" && requiresReview) {
        return { error: REVIEW_MESSAGE, tone: "info" };
    }

    const url = actions[action];
    if (!url) return { error: "js.kanban.not_allowed", tone: "warning" };

    return { spec, url };
}

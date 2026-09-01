/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shortcuts.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Tastenkürzel-Registry + Übersicht (Feature 037, MVP-721; Vollscan G16).
//
// EINE Quelle für alle Kürzel: die Übersicht (`?`) rendert diese Liste, die
// Navigations-Kürzel („g d", „g k", „g p", „n") lesen ihre Ziele aus den
// data-shortcut-*-Attributen des <body> — das Layout setzt sie nur, wenn der
// Nutzer das Recht hat; ohne Attribut bleibt das Kürzel stumm und fehlt in
// der Übersicht. Keine hartkodierten URLs, keine Inline-Skripte (CSP).
//
// Kürzel gelten nie in Eingabefeldern (input/textarea/select/contenteditable)
// und nie mit Modifier außer beim Suchkürzel — sonst kollidieren sie mit dem
// Tippen und mit Browser-Kürzeln.

import { __ } from "./i18n.js";

const DIALOG_ID = "shortcuts-dialog";
const LIST_SELECTOR = "[data-shortcuts-list]";
// Zwei-Tasten-Folgen („g" dann „d"): Zeitfenster für die zweite Taste.
const SEQUENCE_WINDOW_MS = 1000;

/**
 * @typedef {Object} Shortcut
 * @property {string[]} keys      Tasten in Reihenfolge; "Mod" = Cmd (macOS) / Ctrl.
 * @property {string} label_key   Key in lang/{locale}/js.php (über window.__translations).
 * @property {"global"|"navigation"|"search"} scope
 * @property {string} [target]    data-shortcut-<target> am <body>, nur für Navigations-Kürzel.
 * @property {boolean} [sequence] true = Tasten nacheinander (kein Akkord).
 */

/** @type {Shortcut[]} */
export const SHORTCUTS = [
    // Bestand (global-search.js): Cmd/Ctrl+K, Escape, Pfeile/Enter im Dialog.
    { keys: ["Mod", "K"], label_key: "js.shortcuts.search", scope: "global" },
    { keys: ["?"], label_key: "js.shortcuts.shortcuts", scope: "global" },
    // Kontexthilfe (help-drawer.js) — hier nur gelistet, gebunden dort.
    { keys: ["F1"], label_key: "js.shortcuts.help", scope: "global" },
    { keys: ["Esc"], label_key: "js.shortcuts.escape", scope: "global" },
    { keys: ["↑", "↓"], label_key: "js.shortcuts.search_move", scope: "search" },
    { keys: ["↵"], label_key: "js.shortcuts.search_open", scope: "search" },
    // Navigation (MVP-721): Ziel aus body[data-shortcut-*], nur mit Recht.
    { keys: ["g", "d"], label_key: "js.shortcuts.go_diary", scope: "navigation", target: "diary", sequence: true },
    { keys: ["g", "k"], label_key: "js.shortcuts.go_customers", scope: "navigation", target: "customers", sequence: true },
    { keys: ["g", "p"], label_key: "js.shortcuts.go_projects", scope: "navigation", target: "projects", sequence: true },
    { keys: ["n"], label_key: "js.shortcuts.new_entry", scope: "navigation", target: "new-entry" },
];

const SCOPE_ORDER = ["global", "navigation", "search"];

let pendingPrefix = null;
let pendingTimer = null;

function isMac() {
    return /Mac|iPhone|iPad|iPod/.test(navigator.platform || "");
}

/** Tastenbeschriftung für die Übersicht: "Mod" wird plattformabhängig. */
function keyLabel(key) {
    if (key === "Mod") return isMac() ? "⌘" : "Ctrl";
    return key;
}

/** Ziel-URL eines Navigations-Kürzels; null = kein Recht / nicht gesetzt. */
function targetUrl(target) {
    if (!target) return null;
    const value = document.body.getAttribute(`data-shortcut-${target}`);
    return value && value !== "" ? value : null;
}

/** Nur Kürzel, deren Ziel der Nutzer tatsächlich hat. */
export function availableShortcuts() {
    return SHORTCUTS.filter((s) => !s.target || targetUrl(s.target) !== null);
}

function isEditableTarget(target) {
    if (!(target instanceof Element)) return false;
    if (target.closest("input, textarea, select, [contenteditable=''], [contenteditable='true']")) {
        return true;
    }
    return false;
}

function dialog() {
    return /** @type {HTMLDialogElement|null} */ (document.getElementById(DIALOG_ID));
}

function renderList() {
    const list = document.querySelector(LIST_SELECTOR);
    if (!list) return;
    while (list.firstChild) list.removeChild(list.firstChild);

    const items = availableShortcuts();
    for (const scope of SCOPE_ORDER) {
        const scoped = items.filter((s) => s.scope === scope);
        if (scoped.length === 0) continue;

        const heading = document.createElement("h3");
        heading.className = "mt-3 mb-1 text-xs uppercase tracking-wider text-muted first:mt-0";
        heading.textContent = __(`js.shortcuts.scope.${scope}`);
        list.appendChild(heading);

        const dl = document.createElement("dl");
        dl.className = "grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-1.5";
        for (const shortcut of scoped) {
            const dt = document.createElement("dt");
            dt.className = "flex items-center gap-1 whitespace-nowrap";
            shortcut.keys.forEach((key, index) => {
                if (index > 0) {
                    const sep = document.createElement("span");
                    sep.className = "text-xs text-muted";
                    sep.textContent = shortcut.sequence ? __("js.shortcuts.then") : "+";
                    dt.appendChild(sep);
                }
                const kbd = document.createElement("kbd");
                kbd.className = "kbd kbd-sm";
                kbd.textContent = keyLabel(key);
                dt.appendChild(kbd);
            });
            const dd = document.createElement("dd");
            dd.className = "text-sm";
            dd.textContent = __(shortcut.label_key);
            dl.appendChild(dt);
            dl.appendChild(dd);
        }
        list.appendChild(dl);
    }
}

export function openShortcutsDialog() {
    const dlg = dialog();
    if (!dlg) return;
    renderList();
    if (!dlg.hasAttribute("open")) {
        dlg.showModal();
    }
}

export function closeShortcutsDialog() {
    const dlg = dialog();
    if (dlg && dlg.hasAttribute("open")) {
        dlg.close();
    }
}

function clearPending() {
    pendingPrefix = null;
    if (pendingTimer !== null) {
        window.clearTimeout(pendingTimer);
        pendingTimer = null;
    }
}

function navigateTo(target) {
    const url = targetUrl(target);
    if (!url) return false;
    // „Neuer Eintrag" bevorzugt den vorhandenen Modal-Trigger der Seite, damit
    // das Formular wie per Klick als Dialog erscheint statt als nackte Seite.
    const trigger = document.querySelector(
        `a[data-entry-modal-trigger][href="${CSS.escape(url)}"]`,
    );
    if (trigger instanceof HTMLElement) {
        trigger.click();
        return true;
    }
    window.location.assign(url);
    return true;
}

/** @param {KeyboardEvent} e */
function onKeydown(e) {
    // Akkord-Kürzel (Cmd/Ctrl+K) behandelt global-search.js selbst.
    if (e.metaKey || e.ctrlKey || e.altKey) {
        clearPending();
        return;
    }
    if (isEditableTarget(/** @type {Element} */ (e.target))) {
        clearPending();
        return;
    }
    // Ein offener Dialog (Suche, Formular) bekommt die Tasten — nur unsere
    // eigene Übersicht darf per „?" geschlossen/geöffnet werden.
    const openDialog = document.querySelector("dialog[open]");
    if (openDialog && openDialog.id !== DIALOG_ID) {
        clearPending();
        return;
    }

    if (e.key === "?") {
        e.preventDefault();
        clearPending();
        const dlg = dialog();
        if (dlg && dlg.hasAttribute("open")) {
            closeShortcutsDialog();
        } else {
            openShortcutsDialog();
        }
        return;
    }
    if (openDialog) {
        return; // Übersicht ist offen: keine Navigation darunter auslösen.
    }

    const key = e.key.toLowerCase();
    if (pendingPrefix !== null) {
        const prefix = pendingPrefix;
        clearPending();
        const match = SHORTCUTS.find(
            (s) => s.sequence && s.keys.length === 2 && s.keys[0] === prefix && s.keys[1] === key,
        );
        if (match && navigateTo(match.target)) {
            e.preventDefault();
        }
        return;
    }

    const prefixed = SHORTCUTS.some((s) => s.sequence && s.keys[0] === key);
    if (prefixed) {
        pendingPrefix = key;
        pendingTimer = window.setTimeout(clearPending, SEQUENCE_WINDOW_MS);
        return;
    }

    const single = SHORTCUTS.find((s) => !s.sequence && s.keys.length === 1 && s.keys[0] === key && s.target);
    if (single && navigateTo(single.target)) {
        e.preventDefault();
    }
}

function init() {
    if (!dialog()) return;
    document.addEventListener("keydown", onKeydown);
    document.addEventListener("click", (event) => {
        const trigger = /** @type {Element} */ (event.target).closest("[data-shortcuts-trigger]");
        if (trigger) {
            event.preventDefault();
            openShortcutsDialog();
        }
    });
}

document.addEventListener("DOMContentLoaded", init);

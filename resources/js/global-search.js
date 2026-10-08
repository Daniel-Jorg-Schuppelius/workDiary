/*
 * global-search.js
 *
 * Command-Palette / Spotlight für WorkDiary:
 *  - Öffnen über Button [data-global-search-open] oder Cmd/Ctrl+K (oder /).
 *  - Schließen via ESC oder Backdrop.
 *  - Live-Suche (debounced) gegen /api/internal/search.
 *  - Tastatur-Navigation mit ↑/↓ und ↵.
 *  - Aktionen (MVP-1082): Einträge mit `action` statt `url` führen einen
 *    Bedienschritt aus (Farbschema, Tastenkürzel, Kontexthilfe).
 *
 * Erwartet im DOM den Partial `partials/global-search.blade.php`.
 */

import { __ } from "./i18n.js";
import { safeUrl, sameOriginPath, html, setHtml, clearHtml } from "./lib/html.js";
import { getJson } from "./lib/http.js";
import { openShortcutsDialog } from "./shortcuts.js";
import { openContextHelp } from "./help-drawer.js";

const DIALOG_ID = "global-search-dialog";
const DEBOUNCE_MS = 220;
const MIN_LEN = 2;

const searchUrl = () => {
    // Route ist mit web-Middleware registriert; Standardpfad fix verdrahtet.
    return "/api/internal/search";
};

/**
 * Treffer von GlobalSearchController (Palette und GlobalSearchService);
 * der „alle Treffer"-Eintrag trägt nur die URL.
 * @typedef {object} SearchItem
 * @property {number | string} [id]
 * @property {string} [title]
 * @property {string | null} [subtitle]
 * @property {string} [icon]
 * @property {string} [action]
 * @property {string | null} [url]
 */

/**
 * @typedef {object} SearchGroup
 * @property {string} key
 * @property {string} label
 * @property {string} icon
 * @property {SearchItem[]} items
 */

let activeIndex = -1;
/** @type {SearchItem[]} */
let flatItems = [];
/** @type {ReturnType<typeof setTimeout> | null} */
let debounceTimer = null;
let lastQuery = "";

/**
 * @param {Element} root
 * @param {string} text
 * @param {{loading?: boolean}} [options]
 */
const setStatus = (root, text, { loading = false } = {}) => {
    const el = root.querySelector("[data-global-search-status]");
    if (!el) return;
    if (!text) {
        el.classList.add("hidden");
        clearHtml(el);
        return;
    }
    if (loading) {
        setHtml(
            el,
            html`
                <span class="inline-flex items-center gap-2">
                    <span
                        class="loading loading-spinner loading-xs text-primary"
                        aria-hidden="true"
                    ></span>
                    <span>${text}</span>
                </span>
            `,
        );
    } else {
        el.textContent = text;
    }
    el.classList.remove("hidden");
};

/**
 * @param {Element} root
 * @param {string} message
 */
const renderHint = (root, message) => {
    const results = root.querySelector("[data-global-search-results]");
    if (!results) return;
    setHtml(
        results,
        html`<div class="px-4 py-8 text-center text-sm text-muted">
            ${message}
        </div>`,
    );
    flatItems = [];
    activeIndex = -1;
};

/**
 * @param {Element} root
 * @param {SearchGroup[]} groups
 * @param {string | null} [allUrl]
 */
const renderResults = (root, groups, allUrl = null) => {
    const results = root.querySelector("[data-global-search-results]");
    if (!results) return;

    if (!groups || groups.length === 0) {
        renderHint(root, __("Keine Treffer."));
        return;
    }

    const parts = [];
    flatItems = [];
    groups.forEach((group) => {
        parts.push(
            html`<div
                class="px-4 pt-3 pb-1 text-[0.65rem] uppercase tracking-[0.15em] text-muted flex items-center gap-1.5"
            >
                <span
                    class="material-symbols-outlined text-[0.95rem]"
                    aria-hidden="true"
                    >${group.icon || "search"}</span
                >
                <span>${group.label}</span>
            </div>`,
        );
        const items = group.items.map((item) => {
            const idx = flatItems.length;
            flatItems.push(item);
            const content = html`<span
                    class="material-symbols-outlined text-base text-muted"
                    aria-hidden="true"
                    >${item.icon || group.icon || "search"}</span
                >
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium truncate"
                        >${item.title}</span
                    >
                    ${item.subtitle
                        ? html`<span class="block text-xs text-muted truncate"
                              >${item.subtitle}</span
                          >`
                        : ""}
                </span>`;
            if (item.action) {
                return html`<li>
                    <button
                        type="button"
                        data-gs-item
                        data-gs-index="${idx}"
                        data-gs-action="${item.action}"
                        class="flex w-full items-start gap-3 rounded-box px-3 py-2 text-left hover:bg-base-200 focus:bg-base-200 focus:outline-none"
                    >
                        ${content}
                    </button>
                </li>`;
            }
            // safeUrl statt HTML-Escaping: gegen `javascript:` schützt nur eine
            // Protokoll-Allowlist, Entity-Escaping greift im href-Kontext nicht.
            return html`<li>
                <a
                    href="${safeUrl(item.url)}"
                    data-gs-item
                    data-gs-index="${idx}"
                    class="flex items-start gap-3 rounded-box px-3 py-2 hover:bg-base-200 focus:bg-base-200 focus:outline-none"
                >
                    ${content}
                </a>
            </li>`;
        });
        parts.push(
            html`<ul class="px-1">
                ${items}
            </ul>`,
        );
    });
    // Vollaudit 2026-07 (M8): Link auf die Vollergebnisseite mit Filtern.
    if (allUrl) {
        const idx = flatItems.length;
        flatItems.push({ url: allUrl });
        parts.push(
            html`<div class="border-t border-base-200 mt-2 px-1 pt-1">
                <a
                    href="${safeUrl(allUrl)}"
                    data-gs-item
                    data-gs-index="${idx}"
                    class="flex items-center gap-2 rounded-box px-3 py-2 text-sm font-medium hover:bg-base-200 focus:bg-base-200 focus:outline-none"
                >
                    <span
                        class="material-symbols-outlined text-base"
                        aria-hidden="true"
                        >manage_search</span
                    >
                    <span>${__("alle Treffer →")}</span>
                </a>
            </div>`,
        );
    }
    setHtml(results, html`${parts}`);
    activeIndex = -1;
    updateActive(root);
};

/** @param {Element} root */
const updateActive = (root) => {
    const nodes = root.querySelectorAll("[data-gs-item]");
    nodes.forEach((n, i) => {
        if (i === activeIndex) {
            n.classList.add("bg-base-200");
            n.scrollIntoView({ block: "nearest" });
        } else {
            n.classList.remove("bg-base-200");
        }
    });
};

/**
 * @param {Element} root
 * @param {string} term
 */
const fetchResults = async (root, term) => {
    setStatus(root, __("Suche …"), { loading: true });
    try {
        const { ok, data: json } = /** @type {import("./lib/http.js").JsonResult<{ groups?: SearchGroup[], allUrl?: string | null }>} */ (
            await getJson(`${searchUrl()}?q=${encodeURIComponent(term)}`)
        );
        // Veraltete Antwort (neuere, gekürzte oder geleerte Eingabe) nicht rendern.
        if (term !== lastQuery) return;
        if (!ok || !json) {
            setStatus(root, __("Suche fehlgeschlagen."));
            return;
        }
        setStatus(root, "");
        renderResults(root, json.groups || [], json.allUrl || null);
    } catch (e) {
        if (term !== lastQuery) return;
        setStatus(root, __("Suche fehlgeschlagen."));
    }
};

/** @param {Element} root */
const onInput = (root) => {
    const input = /** @type {HTMLInputElement | null} */ (root.querySelector("[data-global-search-input]"));
    if (!input) return;
    const term = (input.value || "").trim();
    lastQuery = term;
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
    if (term.length < MIN_LEN) {
        setStatus(root, "");
        renderHint(
            root,
            __("Geben Sie mindestens 2 Zeichen ein, um Ergebnisse zu sehen."),
        );
        return;
    }
    debounceTimer = setTimeout(() => {
        if (lastQuery === term) fetchResults(root, term);
    }, DEBOUNCE_MS);
};

/**
 * @param {Element} root
 * @param {KeyboardEvent} e
 */
const onKeydown = (root, e) => {
    if (e.key === "ArrowDown") {
        if (flatItems.length === 0) return;
        e.preventDefault();
        activeIndex = (activeIndex + 1) % flatItems.length;
        updateActive(root);
    } else if (e.key === "ArrowUp") {
        if (flatItems.length === 0) return;
        e.preventDefault();
        activeIndex = (activeIndex - 1 + flatItems.length) % flatItems.length;
        updateActive(root);
    } else if (e.key === "Enter") {
        if (activeIndex >= 0 && flatItems[activeIndex]) {
            e.preventDefault();
            if (flatItems[activeIndex].action) {
                runAction(flatItems[activeIndex].action);
                return;
            }
            // Wie der Link selbst: nur Ziele der eigenen Origin.
            const target = sameOriginPath(flatItems[activeIndex].url);
            if (target !== null) window.location.href = target;
        }
    }
};

// Bedienaktionen der Palette (FunctionFinder::ACTIONS): erst schließen, dann
// ausführen — sonst läge ein zweiter Dialog unter dem modalen Suchdialog.
/** @param {string | null | undefined} action */
const runAction = (action) => {
    closeDialog();
    if (action === "theme") {
        const toggle = /** @type {HTMLElement|null} */ (
            document.querySelector("[data-theme-toggle]")
        );
        if (toggle) toggle.click();
    } else if (action === "shortcuts") {
        openShortcutsDialog();
    } else if (action === "help") {
        openContextHelp();
    }
};

const openDialog = () => {
    const dlg = /** @type {HTMLDialogElement} */ (
        document.getElementById(DIALOG_ID)
    );
    if (!dlg) return;
    if (typeof dlg.showModal === "function") {
        try {
            dlg.showModal();
        } catch (_) {
            /* already open */
        }
    } else {
        dlg.setAttribute("open", "");
    }
    const root = dlg.querySelector("[data-global-search-root]");
    const input = /** @type {HTMLInputElement} */ (
        root ? root.querySelector("[data-global-search-input]") : null
    );
    if (input) {
        input.value = "";
        lastQuery = "";
        if (root) {
            setStatus(root, "");
            renderHint(
                root,
                __("Geben Sie mindestens 2 Zeichen ein, um Ergebnisse zu sehen."),
            );
        }
        setTimeout(() => input.focus(), 30);
    }
};

const closeDialog = () => {
    const dlg = /** @type {HTMLDialogElement} */ (
        document.getElementById(DIALOG_ID)
    );
    if (!dlg) return;
    if (typeof dlg.close === "function") {
        try {
            dlg.close();
        } catch (_) {
            /* not open */
        }
    } else {
        dlg.removeAttribute("open");
    }
};

const init = () => {
    const dlg = document.getElementById(DIALOG_ID);
    if (!dlg) return;
    const root = dlg.querySelector("[data-global-search-root]");
    if (!root) return;

    document.querySelectorAll("[data-global-search-open]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            openDialog();
        });
    });

    const input = /** @type {HTMLInputElement | null} */ (root.querySelector("[data-global-search-input]"));
    if (input) {
        input.addEventListener("input", () => onInput(root));
        input.addEventListener("keydown", (e) => onKeydown(root, e));
    }

    root.addEventListener("click", (e) => {
        const button = /** @type {HTMLElement} */ (e.target).closest(
            "[data-gs-action]",
        );
        if (button) runAction(button.getAttribute("data-gs-action"));
    });

    // Globale Tastenkürzel: Cmd/Ctrl+K oder "/" (außerhalb von Eingabefeldern)
    document.addEventListener("keydown", (e) => {
        const isMod = e.metaKey || e.ctrlKey;
        if (isMod && (e.key === "k" || e.key === "K")) {
            e.preventDefault();
            openDialog();
            return;
        }
        if (e.key === "Escape" && dlg.hasAttribute("open")) {
            closeDialog();
        }
    });
};

document.addEventListener("DOMContentLoaded", init);

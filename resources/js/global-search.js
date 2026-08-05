/*
 * global-search.js
 *
 * Command-Palette / Spotlight für WorkDiary:
 *  - Öffnen über Button [data-global-search-open] oder Cmd/Ctrl+K (oder /).
 *  - Schließen via ESC oder Backdrop.
 *  - Live-Suche (debounced) gegen /api/internal/search.
 *  - Tastatur-Navigation mit ↑/↓ und ↵.
 *
 * Erwartet im DOM den Partial `partials/global-search.blade.php`.
 */

import { __ } from "./i18n.js";
import { escHtml, safeUrl, html, setHtml, clearHtml } from "./lib/html.js";

const DIALOG_ID = "global-search-dialog";
const DEBOUNCE_MS = 220;
const MIN_LEN = 2;

const csrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") || "" : "";
};

const searchUrl = () => {
    // Route ist mit web-Middleware registriert; Standardpfad fix verdrahtet.
    return "/api/internal/search";
};

let activeIndex = -1;
let flatItems = [];
let debounceTimer = null;
let lastQuery = "";

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

const renderHint = (root, message) => {
    const results = root.querySelector("[data-global-search-results]");
    if (!results) return;
    setHtml(
        results,
        html`<div class="px-4 py-8 text-center text-sm text-base-content/50">
            ${message}
        </div>`,
    );
    flatItems = [];
    activeIndex = -1;
};

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
                class="px-4 pt-3 pb-1 text-[0.65rem] uppercase tracking-[0.15em] text-base-content/50 flex items-center gap-1.5"
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
            // safeUrl statt HTML-Escaping: gegen `javascript:` schützt nur eine
            // Protokoll-Allowlist, Entity-Escaping greift im href-Kontext nicht.
            return html`<li>
                <a
                    href="${safeUrl(item.url)}"
                    data-gs-item
                    data-gs-index="${idx}"
                    class="flex items-start gap-3 rounded-box px-3 py-2 hover:bg-base-200 focus:bg-base-200 focus:outline-none"
                >
                    <span
                        class="material-symbols-outlined text-base text-base-content/60"
                        aria-hidden="true"
                        >${group.icon || "search"}</span
                    >
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-medium truncate"
                            >${item.title}</span
                        >
                        ${item.subtitle
                            ? html`<span
                                  class="block text-xs text-base-content/60 truncate"
                                  >${item.subtitle}</span
                              >`
                            : ""}
                    </span>
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

const fetchResults = async (root, term) => {
    setStatus(root, __("Suche …"), { loading: true });
    try {
        const res = await fetch(
            `${searchUrl()}?q=${encodeURIComponent(term)}`,
            {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": csrfToken(),
                },
            },
        );
        if (!res.ok) {
            setStatus(root, __("Suche fehlgeschlagen."));
            return;
        }
        const json = await res.json();
        setStatus(root, "");
        renderResults(root, json.groups || [], json.allUrl || null);
    } catch (e) {
        setStatus(root, __("Suche fehlgeschlagen."));
    }
};

const onInput = (root) => {
    const input = root.querySelector("[data-global-search-input]");
    if (!input) return;
    const term = (input.value || "").trim();
    lastQuery = term;
    if (term.length < MIN_LEN) {
        setStatus(root, "");
        renderHint(
            root,
            __("Tippe mindestens 2 Zeichen, um Ergebnisse zu sehen."),
        );
        return;
    }
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        if (lastQuery === term) fetchResults(root, term);
    }, DEBOUNCE_MS);
};

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
            window.location.href = flatItems[activeIndex].url;
        }
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
        if (root)
            renderHint(
                root,
                __("Tippe mindestens 2 Zeichen, um Ergebnisse zu sehen."),
            );
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

    const input = root.querySelector("[data-global-search-input]");
    if (input) {
        input.addEventListener("input", () => onInput(root));
        input.addEventListener("keydown", (e) => onKeydown(root, e));
    }

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

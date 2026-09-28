/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : action-menu.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Aktionsmenüs als <details data-menu> (⋯ im Seitenkopf, <x-action-menu>):
 * schließen bei Klick daneben, bei Escape und nach der gewählten Aktion;
 * es ist immer nur eines offen. Ein <x-action-menu>, das im ⋯-Menü als
 * Abschnitt steht (`data-flattened`), bleibt offen.
 */

const OPEN = "details[data-menu][open]:not([data-flattened])";

/** @param {Element | null} [except] */
function closeMenus(except = null) {
    document.querySelectorAll(OPEN).forEach((menu) => {
        if (menu !== except && !menu.contains(except)) {
            /** @type {HTMLDetailsElement} */ (menu).open = false;
        }
    });
}

/**
 * Ein Menü mit nur einer Aktion (etwa weil die übrigen am Status hängen)
 * wird durch diese Aktion ersetzt; ihre Platzierung erbt sie vom Menü.
 *
 * @param {ParentNode} [root]
 */
export function unwrapSingleActionMenus(root = document) {
    root.querySelectorAll("details[data-action-menu]").forEach((menu) => {
        const items = menu.querySelector(":scope > [data-action-menu-items]");
        if (!items || items.childElementCount !== 1) {
            return;
        }
        const only = /** @type {Element} */ (items.firstElementChild);
        const placement = menu.getAttribute("data-toolbar-placement");
        if (placement && !only.hasAttribute("data-toolbar-placement")) {
            only.setAttribute("data-toolbar-placement", placement);
        }
        menu.replaceWith(only);
    });
}

document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const menu = target?.closest("details[data-menu]") ?? null;
    closeMenus(menu);

    const choice = target?.closest("a[href], button");
    if (menu && choice && menu.contains(choice) && !choice.closest("summary")) {
        // Erst nach dem Klick schließen: Submit, Bestätigungsdialog und
        // Modal-Trigger laufen am document und sollen das Ereignis noch sehen.
        setTimeout(() => closeMenus(), 0);
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
        return;
    }
    const open = document.querySelector(OPEN);
    if (!open) {
        return;
    }
    closeMenus();
    /** @type {HTMLElement | null} */ (open.querySelector(":scope > summary"))?.focus();
});

document.addEventListener("toggle", (event) => {
    const menu = event.target;
    if (menu instanceof HTMLDetailsElement && menu.matches("[data-menu]:not([data-flattened])") && menu.open) {
        closeMenus(menu);
    }
}, true);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => unwrapSingleActionMenus());
} else {
    unwrapSingleActionMenus();
}

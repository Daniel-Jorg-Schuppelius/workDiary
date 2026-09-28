/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : toolbar-overflow.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Seitenkopf mit Überlaufmenü (MVP-966): verteilt die Aktionen von
 * <x-page-toolbar> auf Leiste und ⋯-Menü.
 *
 * Die Knoten werden verschoben, nicht geklont — Formulare, Bestätigungsdialoge
 * und Modal-Trigger arbeiten per Delegation am document weiter. Jeder Lauf
 * setzt erst alle Aktionen in die Leiste zurück, misst und verteilt dann neu;
 * das geschieht synchron, also ohne sichtbares Zwischenbild.
 */

import { headReserve, normalizePlacement, planOverflow } from "./lib/toolbar-overflow.js";
import { unwrapSingleActionMenus } from "./action-menu.js";

const WIDE = window.matchMedia("(min-width: 768px)");

/** @param {Element} el */
function placementOf(el) {
    const carrier = el.hasAttribute("data-toolbar-placement")
        ? el
        : el.querySelector("[data-toolbar-placement]");
    return normalizePlacement(carrier?.getAttribute("data-toolbar-placement"));
}

/**
 * Im ⋯-Menü wird ein <x-action-menu> zum offenen Abschnitt mit seiner
 * Beschriftung als Überschrift — keine Untermenüs auf Touch.
 *
 * @param {Element} menu
 * @param {boolean} flat
 */
function flatten(menu, flat) {
    const details = /** @type {HTMLDetailsElement} */ (menu);
    const summary = details.querySelector(":scope > summary");
    details.toggleAttribute("data-flattened", flat);
    details.open = flat;
    if (flat) {
        summary?.setAttribute("tabindex", "-1");
    } else {
        summary?.removeAttribute("tabindex");
    }
}

/** @param {HTMLElement} toolbar */
function setupToolbar(toolbar) {
    const actions = toolbar.querySelector(":scope > [data-toolbar-actions]");
    const more = /** @type {HTMLDetailsElement | null | undefined} */ (actions?.querySelector(":scope > [data-toolbar-more]"));
    const main = more?.querySelector("[data-toolbar-menu-main]");
    const danger = /** @type {HTMLElement | null | undefined} */ (more?.querySelector("[data-toolbar-menu-danger]"));
    if (!actions || !more || !main || !danger) {
        return;
    }
    unwrapSingleActionMenus(actions);
    const head = toolbar.querySelector(":scope > [data-toolbar-head]");
    const entries = Array.from(actions.children)
        .filter((el) => el !== more && !(el instanceof HTMLTemplateElement) && !(el instanceof HTMLScriptElement))
        .map((el) => ({ el, placement: placementOf(el) }));

    toolbar.classList.add("wd-toolbar-js");
    let lastWidth = -1;

    const run = (force = false) => {
        const width = toolbar.clientWidth;
        if (!force && width === lastWidth) {
            return;
        }
        lastWidth = width;

        more.open = false;
        entries.forEach(({ el }) => actions.insertBefore(el, more));
        more.hidden = false;
        toolbar.classList.remove("wd-toolbar-compact");

        const style = getComputedStyle(toolbar);
        const inner = width - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        const rootFont = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
        const reserve = head && WIDE.matches
            ? headReserve(inner, rootFont) + (parseFloat(style.columnGap) || 0)
            : 0;
        const plan = planOverflow(
            entries.map(({ el, placement }) => ({ width: el.getBoundingClientRect().width, placement })),
            inner - reserve,
            more.getBoundingClientRect().width,
            parseFloat(getComputedStyle(actions).columnGap) || 0,
        );

        plan.menu.forEach((index) => {
            const { el, placement } = entries[index];
            (placement === "danger" ? danger : main).appendChild(el);
        });
        more.hidden = plan.menu.length === 0;
        danger.hidden = !plan.menu.some((index) => entries[index].placement === "danger");
        more.querySelectorAll("details[data-action-menu]").forEach((menu) => flatten(menu, true));
        actions.querySelectorAll(":scope > details[data-action-menu]").forEach((menu) => flatten(menu, false));

        if (plan.compact) {
            toolbar.classList.add("wd-toolbar-compact");
            actions.querySelectorAll(":scope > * .btn, :scope > .btn").forEach((btn) => {
                if (!btn.closest("[data-toolbar-more]") && !btn.hasAttribute("title")) {
                    btn.setAttribute("title", (btn.textContent ?? "").trim());
                }
            });
        }
    };

    new ResizeObserver(() => run()).observe(toolbar);
    WIDE.addEventListener?.("change", () => run(true));
    document.fonts?.ready.then(() => run(true));
    run(true);
}

/** @param {ParentNode} [root] */
export function initToolbars(root = document) {
    root.querySelectorAll("[data-toolbar]").forEach((toolbar) => {
        if (!(toolbar instanceof HTMLElement) || toolbar.dataset.toolbarReady === "1") {
            return;
        }
        toolbar.dataset.toolbarReady = "1";
        setupToolbar(toolbar);
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initToolbars());
} else {
    initToolbars();
}

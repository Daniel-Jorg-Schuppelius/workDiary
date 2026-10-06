/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : workspace-editor.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Editor für eigene Arbeitsbereiche (Feature 082 Phase 2, MVP-731).
 *
 * Zwei gleichwertige Wege, die Reihenfolge zu bestimmen — das ist die
 * eigentliche Anforderung: Ziehen am Griff über lib/pointer-sort.js (Maus,
 * Touch, Stift in einem Codepfad) UND Schaltflächen bzw. Pfeiltasten am
 * Griff. Ohne Zeigegerät ist der Editor damit vollständig bedienbar.
 *
 * Event-Delegation auf `document` (Konvention: inline-actions.js,
 * contact-persons.js) — greift dadurch auch im per AJAX nachgeladenen
 * Dialoginhalt. Keine Inline-Handler, keine Inline-Skripte (CSP Stufe 2).
 *
 * Markup-Kontrakt:
 *   [data-workspace-editor]   Wurzel
 *   [data-workspace-catalog]  Katalogspalte
 *   [data-workspace-add]      Katalog-Eintrag (data-key)
 *   [data-workspace-filter]   Suchfeld über dem Katalog
 *   [data-workspace-order]    <ol> der Auswahl (Reihenfolge = Speicherfolge)
 *   [data-workspace-chip]     eine Auswahlzeile (data-key + hidden items[])
 *   [data-workspace-handle]   Ziehgriff (auch Tastaturziel)
 *   [data-workspace-up/down]  Tastatur-/Klick-Alternative zum Ziehen
 *   [data-workspace-remove]   Zeile entfernen
 *   [data-workspace-template] <template> einer Auswahlzeile
 *   [data-workspace-count]    Zähler, [data-workspace-empty] Leerhinweis
 */

import { pointerSort } from "./lib/pointer-sort.js";

/**
 * @param {Element | null} node
 * @returns {HTMLElement | null}
 */
function editorOf(node) {
    return /** @type {HTMLElement | null} */ (
        node ? node.closest("[data-workspace-editor]") : null
    );
}

/**
 * @param {HTMLElement} root
 * @returns {HTMLElement | null}
 */
function orderList(root) {
    return /** @type {HTMLElement | null} */ (
        root.querySelector("[data-workspace-order]")
    );
}

/**
 * @param {HTMLElement} list
 * @returns {HTMLElement[]}
 */
function chipsOf(list) {
    return /** @type {HTMLElement[]} */ (
        Array.from(list.querySelectorAll("[data-workspace-chip]"))
    );
}

/**
 * Zähler, Leerhinweis und Katalogzustand nachziehen.
 *
 * @param {HTMLElement} root
 */
function refresh(root) {
    const list = orderList(root);
    if (!list) return;
    const chips = chipsOf(list);
    const keys = new Set(chips.map((chip) => chip.dataset.key || ""));

    const count = root.querySelector("[data-workspace-count]");
    if (count) count.textContent = String(chips.length);

    const empty = root.querySelector("[data-workspace-empty]");
    if (empty) empty.classList.toggle("hidden", chips.length > 0);

    root.querySelectorAll("[data-workspace-add]").forEach((node) => {
        const button = /** @type {HTMLButtonElement} */ (node);
        const chosen = keys.has(button.dataset.key || "");
        button.disabled = chosen;
        button.classList.toggle("opacity-40", chosen);
        button.setAttribute("aria-pressed", chosen ? "true" : "false");
    });
}

/**
 * Katalogeintrag zur Auswahl hinzufügen (ans Ende — dort schaut man hin).
 *
 * @param {HTMLElement} root
 * @param {HTMLElement} button
 */
function addKey(root, button) {
    const list = orderList(root);
    const template = /** @type {HTMLTemplateElement | null} */ (
        root.querySelector("[data-workspace-template]")
    );
    const key = button.dataset.key || "";
    if (!list || !template || key === "") return;
    if (chipsOf(list).some((chip) => chip.dataset.key === key)) return;

    const fragment = /** @type {DocumentFragment} */ (
        template.content.cloneNode(true)
    );
    const chip = /** @type {HTMLElement | null} */ (
        fragment.querySelector("[data-workspace-chip]")
    );
    if (!chip) return;

    chip.dataset.key = key;
    const input = /** @type {HTMLInputElement | null} */ (
        chip.querySelector('input[name="items[]"]')
    );
    if (input) input.value = key;

    const label = chip.querySelector("[data-workspace-label]");
    const source = button.querySelector("[data-workspace-text]");
    if (label) label.textContent = (source?.textContent || key).trim();

    const icon = chip.querySelector("[data-workspace-icon]");
    const sourceIcon = button.querySelector("[data-icon]");
    const iconName = sourceIcon instanceof HTMLElement ? sourceIcon.dataset.icon : null;
    if (icon && iconName) {
        icon.textContent = iconName;
        if (icon instanceof HTMLElement) icon.dataset.icon = iconName;
    }

    list.appendChild(chip);
    refresh(root);
}

/**
 * Zeile um eine Position verschieben (Tastatur-/Klick-Alternative).
 *
 * @param {HTMLElement} chip
 * @param {-1 | 1} direction
 */
function move(chip, direction) {
    const sibling =
        direction < 0
            ? chip.previousElementSibling
            : chip.nextElementSibling;
    if (!(sibling instanceof HTMLElement)) return;
    if (direction < 0) sibling.before(chip);
    else sibling.after(chip);
}

document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const root = editorOf(target);
    if (!root) return;

    const add = /** @type {HTMLElement | null} */ (
        target.closest("[data-workspace-add]")
    );
    if (add) {
        event.preventDefault();
        addKey(root, add);
        return;
    }

    const chip = /** @type {HTMLElement | null} */ (
        target.closest("[data-workspace-chip]")
    );
    if (!chip) return;

    if (target.closest("[data-workspace-remove]")) {
        event.preventDefault();
        chip.remove();
        refresh(root);
        return;
    }
    if (target.closest("[data-workspace-up]")) {
        event.preventDefault();
        move(chip, -1);
        return;
    }
    if (target.closest("[data-workspace-down]")) {
        event.preventDefault();
        move(chip, 1);
    }
});

// Tastatur-Alternative direkt am Griff: Pfeil hoch/runter verschiebt.
document.addEventListener("keydown", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target || !target.closest("[data-workspace-handle]")) return;
    const chip = /** @type {HTMLElement | null} */ (
        target.closest("[data-workspace-chip]")
    );
    if (!chip) return;

    if (event.key === "ArrowUp") {
        event.preventDefault();
        move(chip, -1);
        /** @type {HTMLElement | null} */ (
            chip.querySelector("[data-workspace-handle]")
        )?.focus();
    } else if (event.key === "ArrowDown") {
        event.preventDefault();
        move(chip, 1);
        /** @type {HTMLElement | null} */ (
            chip.querySelector("[data-workspace-handle]")
        )?.focus();
    }
});

// Die Reihenfolge steht schon während des Zugs im DOM (= im Formular).
// Delegation über document, weil der Dialog nachgeladen wird.
pointerSort(document, {
    list: "[data-workspace-order]",
    item: "[data-workspace-chip]",
    handle: "[data-workspace-handle]",
    mode: "live",
    draggingClass: ["opacity-60", "ring-1", "ring-primary"],
});

// Katalogfilter: rein visuell, die Auswahl bleibt unberührt.
document.addEventListener("input", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target || !target.matches("[data-workspace-filter]")) return;
    const root = editorOf(target);
    if (!root) return;

    const needle = (
        /** @type {HTMLInputElement} */ (target).value || ""
    )
        .trim()
        .toLowerCase();

    root.querySelectorAll("[data-workspace-add]").forEach((node) => {
        const button = /** @type {HTMLElement} */ (node);
        const text = (button.textContent || "").toLowerCase();
        button.classList.toggle("hidden", needle !== "" && !text.includes(needle));
    });
    root.querySelectorAll("[data-workspace-section]").forEach((node) => {
        const section = /** @type {HTMLElement} */ (node);
        const visible = Array.from(
            section.querySelectorAll("[data-workspace-add]"),
        ).some((button) => !button.classList.contains("hidden"));
        section.classList.toggle("hidden", !visible);
    });
});

/**
 * Erstzustand herstellen — der Katalog muss beim Öffnen wissen, was bereits
 * gewählt ist.
 */
function initAll() {
    document
        .querySelectorAll("[data-workspace-editor]")
        .forEach((node) => refresh(/** @type {HTMLElement} */ (node)));
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
} else {
    initAll();
}

// Der Dialog kommt per AJAX in den Modal-Host; ein Beobachter ist hier
// ehrlicher als ein Event, das app.js gar nicht auslöst.
if (typeof MutationObserver !== "undefined") {
    new MutationObserver((records) => {
        for (const record of records) {
            for (const node of Array.from(record.addedNodes)) {
                if (!(node instanceof Element)) continue;
                if (
                    node.matches("[data-workspace-editor]") ||
                    node.querySelector("[data-workspace-editor]")
                ) {
                    initAll();
                    return;
                }
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
}

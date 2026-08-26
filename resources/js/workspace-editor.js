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
 * eigentliche Anforderung: Drag-and-drop über **Pointer Events** (Maus,
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
 *
 * Ausserdem: [data-workspace-activate] (Liste) schaltet über den bestehenden
 * Server-Endpunkt auf einen Arbeitsbereich um — Formular-POST über lib/http.js.
 */

import { submitForm } from "./lib/http.js";

/** Pixel, ab denen aus einem Klick ein Ziehen wird. */
const DRAG_THRESHOLD = 4;

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

/** @type {{chip: HTMLElement, root: HTMLElement, pointerId: number, startY: number, dragging: boolean} | null} */
let drag = null;

/**
 * Zeile, über der der Zeiger gerade steht (Mittelpunktvergleich — stabiler
 * als elementFromPoint, wenn die gezogene Zeile unter dem Zeiger klebt).
 *
 * @param {HTMLElement} list
 * @param {HTMLElement} chip
 * @param {number} clientY
 */
function reorderTo(list, chip, clientY) {
    for (const other of chipsOf(list)) {
        if (other === chip) continue;
        const box = other.getBoundingClientRect();
        const middle = box.top + box.height / 2;
        if (clientY < middle) {
            if (other.previousElementSibling !== chip) other.before(chip);
            return;
        }
    }
    if (list.lastElementChild !== chip) list.appendChild(chip);
}

document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    // Arbeitsbereich aus der Liste heraus aktivieren (Server entscheidet).
    const activate = /** @type {HTMLElement | null} */ (
        target.closest("[data-workspace-activate]")
    );
    if (activate) {
        event.preventDefault();
        const url = activate.dataset.url || "";
        if (url !== "") submitForm(url);
        return;
    }

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

document.addEventListener("pointerdown", (event) => {
    if (event.button !== 0 && event.pointerType === "mouse") return;
    const target = event.target instanceof Element ? event.target : null;
    if (!target || !target.closest("[data-workspace-handle]")) return;
    const chip = /** @type {HTMLElement | null} */ (
        target.closest("[data-workspace-chip]")
    );
    const root = editorOf(target);
    if (!chip || !root) return;

    drag = {
        chip,
        root,
        pointerId: event.pointerId,
        startY: event.clientY,
        dragging: false,
    };
});

document.addEventListener("pointermove", (event) => {
    if (!drag || event.pointerId !== drag.pointerId) return;
    const list = orderList(drag.root);
    if (!list) return;

    if (!drag.dragging) {
        if (Math.abs(event.clientY - drag.startY) < DRAG_THRESHOLD) return;
        drag.dragging = true;
        drag.chip.classList.add("opacity-60", "ring-1", "ring-primary");
        try {
            drag.chip.setPointerCapture(event.pointerId);
        } catch (_e) {
            /* ältere Engines ohne Pointer-Capture */
        }
    }

    event.preventDefault();
    reorderTo(list, drag.chip, event.clientY);
});

/** Ziehen beenden — Reihenfolge steht bereits im DOM (= im Formular). */
function endDrag() {
    if (!drag) return;
    drag.chip.classList.remove("opacity-60", "ring-1", "ring-primary");
    drag = null;
}

document.addEventListener("pointerup", endDrag);
document.addEventListener("pointercancel", endDrag);

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

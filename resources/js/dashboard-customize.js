/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard-customize.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * „Dashboard anpassen": Sortieren der Kachel-Liste per Drag & Drop oder
 * Pfeiltasten, Sichtbarkeits-Schalter und die Verwaltung der Bereiche (Tabs).
 *
 * Gespeichert wird erst beim Absenden des Formulars — die Reihenfolge steckt
 * allein in der Position der Zeilen, deshalb müssen nach jeder Bewegung die
 * Feldnamen (widgets[i][…] bzw. tabs[i][…]) neu durchnummeriert werden. Die
 * Pfeil-Buttons bleiben als Tastatur-/A11y-Pfad neben dem Ziehen erhalten.
 *
 * Bereiche werden ohne Zwischenspeichern angelegt: ein neuer Bereich taucht
 * sofort in jedem Kachel-Auswahlfeld auf, damit Anlegen und Zuordnen ein
 * Arbeitsgang bleiben.
 */

/** @param {HTMLElement} list */
function reindexWidgets(list) {
    list.querySelectorAll("[data-widget-row]").forEach((row, index) => {
        const key = row.querySelector(".widget-key-input");
        const hidden = row.querySelector(".widget-hidden-input");
        const width = row.querySelector("[data-widget-width]");
        const tab = row.querySelector("[data-widget-tab]");
        if (key instanceof HTMLInputElement) key.name = `widgets[${index}][key]`;
        if (hidden instanceof HTMLInputElement) hidden.name = `widgets[${index}][hidden]`;
        if (width instanceof HTMLSelectElement) width.name = `widgets[${index}][width]`;
        if (tab instanceof HTMLSelectElement) tab.name = `widgets[${index}][tab]`;
    });
}

/** @param {HTMLElement} tabList */
function reindexTabs(tabList) {
    tabList.querySelectorAll("[data-tab-row]").forEach((row, index) => {
        const label = row.querySelector("[data-tab-label]");
        const key = row.querySelector("[data-tab-key-input]");
        const icon = row.querySelector("[data-tab-icon]");
        if (label instanceof HTMLInputElement) label.name = `tabs[${index}][label]`;
        if (key instanceof HTMLInputElement) key.name = `tabs[${index}][key]`;
        if (icon instanceof HTMLInputElement) icon.name = `tabs[${index}][icon]`;
    });
}

/**
 * Aktuelle Bereiche als [{key, label}] in Listenreihenfolge.
 *
 * @param {HTMLElement} tabList
 * @returns {Array<{key: string, label: string}>}
 */
function readTabs(tabList) {
    /** @type {Array<{key: string, label: string}>} */
    const tabs = [];
    tabList.querySelectorAll("[data-tab-row]").forEach((row) => {
        const keyInput = row.querySelector("[data-tab-key-input]");
        const labelInput = row.querySelector("[data-tab-label]");
        if (!(keyInput instanceof HTMLInputElement) || !(labelInput instanceof HTMLInputElement)) return;
        const label = labelInput.value.trim();
        if (keyInput.value && label) tabs.push({ key: keyInput.value, label });
    });
    return tabs;
}

/**
 * Symbol-Vorschau einer Bereichszeile nachziehen. Die Icon-Komponente rendert
 * den Namen als Textinhalt; leer heißt „kein Symbol" und zeigt das Standard-
 * Symbol, damit die Zeile nicht springt.
 *
 * @param {HTMLElement} row
 */
function syncIconPreview(row) {
    const input = row.querySelector("[data-tab-icon]");
    const preview = row.querySelector("[data-tab-icon-preview]");
    if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLElement)) return;
    const name = input.value.trim() || "tab";
    preview.textContent = name;
    preview.dataset.icon = name;
}

/**
 * Spiegelt die Bereiche in jedes Kachel-Auswahlfeld. Die leere Option („immer
 * sichtbar", also über der Leiste) bleibt immer die erste; eine Zuordnung auf
 * einen gelöschten Bereich fällt genau darauf zurück — so wie es das Dashboard
 * beim Rendern auch tut.
 *
 * @param {HTMLElement} root
 */
function syncTabOptions(root) {
    const tabList = root.querySelector("[data-tab-list]");
    const widgetList = root.querySelector("[data-widget-list]");
    if (!(tabList instanceof HTMLElement) || !(widgetList instanceof HTMLElement)) return;

    const tabs = readTabs(tabList);
    const keys = tabs.map((t) => t.key);

    const empty = root.querySelector("[data-tab-empty]");
    if (empty instanceof HTMLElement) empty.hidden = tabs.length > 0;

    widgetList.querySelectorAll("[data-widget-tab]").forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) return;

        const wrap = select.closest("[data-widget-tab-wrap]");
        if (wrap instanceof HTMLElement) wrap.hidden = tabs.length === 0;

        const previous = select.value;
        const alwaysLabel = select.dataset.alwaysLabel || "";
        select.replaceChildren();

        const always = document.createElement("option");
        always.value = "";
        always.textContent = alwaysLabel;
        select.appendChild(always);

        tabs.forEach((tab) => {
            const option = document.createElement("option");
            option.value = tab.key;
            option.textContent = tab.label;
            select.appendChild(option);
        });
        select.value = keys.includes(previous) ? previous : "";
    });
}

/** @param {HTMLElement} root */
function initTabs(root) {
    const tabList = root.querySelector("[data-tab-list]");
    const template = root.querySelector("[data-tab-template]");
    const addButton = root.querySelector("[data-tab-add]");
    if (!(tabList instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) return;

    // Schlüssel sind stabil und landen in Alpine-Attributen des Dashboards —
    // deshalb generiert, nicht aus der Beschriftung abgeleitet.
    const nextKey = () => {
        const used = readTabs(tabList).map((t) => t.key);
        let n = used.length + 1;
        while (used.includes(`tab-${n}`)) n += 1;
        return `tab-${n}`;
    };

    tabList.querySelectorAll("[data-tab-row]").forEach((row) => {
        if (row instanceof HTMLElement) syncIconPreview(row);
    });

    addButton?.addEventListener("click", () => {
        const fragment = template.content.cloneNode(true);
        if (!(fragment instanceof DocumentFragment)) return;
        const row = fragment.querySelector("[data-tab-row]");
        const keyInput = fragment.querySelector("[data-tab-key-input]");
        const labelInput = fragment.querySelector("[data-tab-label]");
        if (!(row instanceof HTMLElement) || !(keyInput instanceof HTMLInputElement) || !(labelInput instanceof HTMLInputElement)) return;

        const key = nextKey();
        row.dataset.tabKey = key;
        keyInput.value = key;
        labelInput.value = `${labelInput.dataset.defaultLabel || "Bereich"} ${readTabs(tabList).length + 1}`;

        tabList.appendChild(fragment);
        const added = tabList.lastElementChild;
        if (added instanceof HTMLElement) syncIconPreview(added);
        reindexTabs(tabList);
        syncTabOptions(root);
        labelInput.focus();
        labelInput.select();
    });

    tabList.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        // Symbol aus dem Raster übernehmen: Feld füllen, Vorschau nachziehen,
        // Raster wieder zuklappen.
        const pick = target.closest("[data-icon-pick]");
        if (pick instanceof HTMLElement) {
            const row = pick.closest("[data-tab-row]");
            const input = row?.querySelector("[data-tab-icon]");
            if (row instanceof HTMLElement && input instanceof HTMLInputElement) {
                input.value = pick.dataset.iconPick || "";
                syncIconPreview(row);
                const details = pick.closest("details");
                if (details instanceof HTMLDetailsElement) details.open = false;
            }
            return;
        }

        if (!target.closest("[data-tab-remove]")) return;

        const row = target.closest("[data-tab-row]");
        if (!(row instanceof HTMLElement)) return;

        row.remove();
        reindexTabs(tabList);
        syncTabOptions(root);
    });

    tabList.addEventListener("input", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;

        if (target.matches("[data-tab-label]")) {
            syncTabOptions(root);
        } else if (target.matches("[data-tab-icon]")) {
            const row = target.closest("[data-tab-row]");
            if (row instanceof HTMLElement) syncIconPreview(row);
        }
    });
}

/**
 * @param {HTMLElement} root
 * @param {HTMLElement} list
 */
function initSorting(root, list) {
    /** @type {HTMLElement | null} */
    let dragRow = null;

    const clearMarkers = () => {
        list.querySelectorAll("[data-widget-row]").forEach((row) => {
            row.classList.remove("opacity-50", "outline", "outline-primary");
        });
    };

    list.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const up = target.closest(".widget-move-up");
        const down = target.closest(".widget-move-down");
        if (!up && !down) return;

        const row = target.closest("[data-widget-row]");
        if (!row || !row.parentNode) return;

        if (up && row.previousElementSibling) {
            row.parentNode.insertBefore(row, row.previousElementSibling);
            reindexWidgets(list);
        } else if (down && row.nextElementSibling) {
            row.parentNode.insertBefore(row.nextElementSibling, row);
            reindexWidgets(list);
        }
    });

    list.addEventListener("change", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (!target.classList.contains("widget-visible-toggle")) return;

        const row = target.closest("[data-widget-row]");
        const hidden = row?.querySelector(".widget-hidden-input");
        if (hidden instanceof HTMLInputElement) {
            hidden.value = target.checked ? "0" : "1";
        }
        // Zustand sofort sichtbar machen — sonst sieht man erst nach dem
        // Speichern, welche Kacheln aus sind.
        if (row instanceof HTMLElement) {
            row.classList.toggle("bg-base-200/60", !target.checked);
            row.classList.toggle("border-l-2", !target.checked);
            row.classList.toggle("border-l-base-300", !target.checked);
        }
    });

    list.addEventListener("dragstart", (event) => {
        const target = event.target;
        const row = target instanceof Element ? target.closest("[data-widget-row]") : null;
        if (!(row instanceof HTMLElement)) return;

        dragRow = row;
        row.classList.add("opacity-50");
        if (event instanceof DragEvent && event.dataTransfer) {
            event.dataTransfer.effectAllowed = "move";
            try {
                event.dataTransfer.setData("text/plain", row.dataset.widgetKey || "");
            } catch (_e) {
                /* ältere Engines */
            }
        }
    });

    list.addEventListener("dragover", (event) => {
        if (!dragRow) return;
        event.preventDefault();

        const target = event.target;
        const row = target instanceof Element ? target.closest("[data-widget-row]") : null;
        if (!(row instanceof HTMLElement) || row === dragRow) return;

        row.classList.add("outline", "outline-primary");
        if (event instanceof DragEvent && event.dataTransfer) {
            event.dataTransfer.dropEffect = "move";
        }
    });

    list.addEventListener("dragleave", (event) => {
        const target = event.target;
        const row = target instanceof Element ? target.closest("[data-widget-row]") : null;
        if (row instanceof HTMLElement) {
            row.classList.remove("outline", "outline-primary");
        }
    });

    list.addEventListener("drop", (event) => {
        if (!dragRow) return;
        event.preventDefault();

        const target = event.target;
        const row = target instanceof Element ? target.closest("[data-widget-row]") : null;
        if (row instanceof HTMLElement && row !== dragRow && row.parentNode) {
            // Oberhalb der Mitte einfügen, sonst darunter — sonst „springt"
            // die Zeile beim Ablegen am unteren Rand eine Position zu weit.
            const box = row.getBoundingClientRect();
            const before = event instanceof DragEvent && event.clientY < box.top + box.height / 2;
            row.parentNode.insertBefore(dragRow, before ? row : row.nextElementSibling);
            reindexWidgets(list);
        }

        clearMarkers();
        dragRow = null;
    });

    list.addEventListener("dragend", () => {
        clearMarkers();
        dragRow = null;
    });
}

function init() {
    const root = /** @type {HTMLElement | null} */ (
        document.querySelector("[data-dashboard-customize]")
    );
    if (!root) return;

    const list = /** @type {HTMLElement | null} */ (
        root.querySelector("[data-widget-list]")
    );
    if (!list) return;

    initSorting(root, list);
    initTabs(root);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}

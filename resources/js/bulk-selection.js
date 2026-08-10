/*
 * bulk-selection.js
 *
 * Verkabelt das <x-bulk-toolbar>-Pattern für Massenaktionen:
 *  - data-bulk-form           : Formular-Wurzel (kapselt alle Checkboxen + Toolbar)
 *  - data-bulk-select-all     : Header-Checkbox (toggelt alle sichtbaren Row-Checkboxen)
 *  - data-bulk-checkbox       : Row-Checkbox (input[type=checkbox] mit name="ids[]")
 *  - data-bulk-toolbar        : Sticky-Toolbar (wird ein-/ausgeblendet)
 *  - data-bulk-counter        : Zähler-Element innerhalb der Toolbar
 *  - data-bulk-clear          : Button "Auswahl aufheben"
 *  - data-bulk-dialog-link    : <a data-entry-modal-trigger>, dessen href bei
 *                               jeder Auswahl-Änderung die gewählten Werte als
 *                               ids[]-Query erhält (Massenaktion als Dialog)
 *  - data-bulk-ids-form       : Formular (z. B. in der Toolbar), das bei jeder
 *                               Auswahl-Änderung versteckte ids[]-Inputs der
 *                               gewählten Zeilen erhält (POST-Massenaktion,
 *                               auch wenn die Wurzel ein <div> ist)
 *
 * Aktionen werden über Submit-Buttons mit formaction="<route>" innerhalb der Form
 * ausgelöst; keine zusätzliche Routing-Logik nötig. Die Wurzel darf auch ein
 * <div> sein (Dialog-Link statt Submit) — dann dürfen Zeilen eigene Formulare
 * enthalten (z. B. Löschen), die der Submit-Guard nicht anfasst.
 */

const init = (root) => {
    if (!root || root.dataset.bulkInitialised === "1") return;
    root.dataset.bulkInitialised = "1";

    const toolbar = root.querySelector("[data-bulk-toolbar]");
    const counter = root.querySelector("[data-bulk-counter]");
    const selectAll = root.querySelector("[data-bulk-select-all]");
    const clearBtn = root.querySelector("[data-bulk-clear]");

    const checkboxes = () =>
        Array.from(root.querySelectorAll("[data-bulk-checkbox]"));

    const refresh = () => {
        const boxes = checkboxes();
        const selected = boxes.filter((b) => b.checked);
        const count = selected.length;

        if (counter) counter.textContent = String(count);
        if (toolbar) {
            toolbar.classList.toggle("hidden", count === 0);
            toolbar.classList.toggle("flex", count > 0);
        }
        if (selectAll) {
            selectAll.indeterminate = count > 0 && count < boxes.length;
            selectAll.checked = boxes.length > 0 && count === boxes.length;
        }

        root.querySelectorAll("[data-bulk-dialog-link]").forEach((link) => {
            if (!link.dataset.bulkDialogBase) {
                link.dataset.bulkDialogBase = link.getAttribute("href") ?? "";
            }
            const params = new URLSearchParams();
            selected.forEach((b) => params.append("ids[]", b.value));
            link.setAttribute(
                "href",
                count > 0
                    ? `${link.dataset.bulkDialogBase}?${params.toString()}`
                    : link.dataset.bulkDialogBase,
            );
        });

        // Auswahl in Toolbar-Formulare spiegeln — synchron statt beim Submit,
        // damit auch programmatische Submits (confirm-dialog) die ids tragen.
        root.querySelectorAll("[data-bulk-ids-form]").forEach((form) => {
            form.querySelectorAll("input[data-bulk-injected]").forEach((i) =>
                i.remove(),
            );
            selected.forEach((b) => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "ids[]";
                input.value = b.value;
                input.dataset.bulkInjected = "1";
                form.appendChild(input);
            });
        });
    };

    if (selectAll) {
        selectAll.addEventListener("change", () => {
            const target = selectAll.checked;
            checkboxes().forEach((b) => {
                if (!b.disabled) b.checked = target;
            });
            refresh();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener("click", (e) => {
            e.preventDefault();
            checkboxes().forEach((b) => {
                b.checked = false;
            });
            refresh();
        });
    }

    root.addEventListener("change", (e) => {
        if (e.target && e.target.matches("[data-bulk-checkbox]")) {
            refresh();
        }
    });

    // Submit-Guard: wenn nichts ausgewählt → blockieren. Nur für die
    // Bulk-Form selbst — bubbelnde Submits innerer Zeilen-Formulare
    // (Löschen) gehen den Guard nichts an.
    root.addEventListener("submit", (e) => {
        if (e.target !== root) return;
        const count = checkboxes().filter((b) => b.checked).length;
        if (count === 0) {
            e.preventDefault();
            if (typeof window.notifyAction === "function") {
                window.notifyAction({
                    tone: "warning",
                    message: "Bitte zuerst mindestens einen Eintrag auswählen.",
                });
            }
        }
    });

    refresh();
};

const initAll = (scope = document) => {
    scope.querySelectorAll("[data-bulk-form]").forEach(init);
};

document.addEventListener("DOMContentLoaded", () => initAll());
document.addEventListener("livewire:navigated", () => initAll());

export { init, initAll };

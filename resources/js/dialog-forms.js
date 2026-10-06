/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dialog-forms.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Verhalten von Formularen, die als Dialog-Fragment nachgeladen werden.
// Ein <script> im Fragment führt der Browser nicht aus (innerHTML), deshalb
// bindet initDynamicFields() in app.js diese Bausteine nach jedem Laden.

/** Auslagen: Kategorie setzt Steuersatz und Abrechenbar-Vorgabe, zeigt den Verpflegungshinweis. */
function initExpenseCategory(root) {
    root.querySelectorAll("[data-expense-category]").forEach((select) => {
        if (select.dataset.expenseBound === "1") return;
        select.dataset.expenseBound = "1";

        const scope = select.closest("form") || root;
        const taxInput = scope.querySelector("[data-expense-tax-rate]");
        const billable = scope.querySelector("[data-expense-billable]");
        const mealsHint = scope.querySelector("[data-meals-hint]");

        const refreshMealsHint = () => {
            if (!mealsHint) return;
            const option = select.options[select.selectedIndex];
            mealsHint.classList.toggle(
                "hidden",
                !(option && option.dataset.slug === "meals"),
            );
        };

        refreshMealsHint();
        select.addEventListener("change", () => {
            const option = select.options[select.selectedIndex];
            if (!option) return;
            // Steuersatz nur vorbelegen, solange das Feld leer ist.
            if (
                taxInput &&
                taxInput.value.trim() === "" &&
                option.dataset.taxRate
            ) {
                taxInput.value = option.dataset.taxRate;
            }
            if (
                billable &&
                option.dataset.billableDefault === "1" &&
                !billable.dataset.userTouched
            ) {
                billable.checked = true;
            }
            refreshMealsHint();
        });
        if (billable) {
            billable.addEventListener("change", () => {
                billable.dataset.userTouched = "1";
            });
        }
    });
}

function parseJson(raw) {
    try {
        const value = JSON.parse(raw || "{}");
        return value && typeof value === "object" ? value : {};
    } catch (_error) {
        return {};
    }
}

function optionLabel(select, value) {
    if (!select) return "";
    const option = Array.prototype.find.call(
        select.options,
        (candidate) => candidate.value === value,
    );
    return option ? option.textContent.trim() : value;
}

/** Pflichtklassifikation: Auftragstyp und Pflicht-Domain belegen Phase, Schweregrad und Anzahl vor. */
function initRequirementPresets(root) {
    const entryType = root.querySelector("#req-entry-type");
    const domain = root.querySelector("#req-domain");
    if (!entryType || !domain || entryType.dataset.presetBound === "1") return;
    entryType.dataset.presetBound = "1";

    const scope = entryType.closest("form") || root;
    const entryTypePresets = parseJson(entryType.dataset.entryTypePresets);
    const domainPresets = parseJson(domain.dataset.requiredDomainPresets);
    const summary = scope.querySelector("#req-preset-summary");
    const details = scope.querySelector("#req-preset-details");
    const labels = parseJson(summary ? summary.dataset.presetLabels : "{}");
    const text = (key) => labels[key] || "";
    const fields = {};
    ["enforce_phase", "severity", "min_count", "max_count", "allow_multi"].forEach(
        (name) => {
            fields[name] = scope.querySelector(`[data-preset-target="${name}"]`);
        },
    );

    const combinedPreset = () => ({
        ...(domainPresets[domain.value] || {}),
        ...(entryTypePresets[entryType.value] || {}),
    });

    const updateHint = () => {
        if (!summary) return;
        const domainPreset = domainPresets[domain.value];
        const entryTypePreset = entryTypePresets[entryType.value];
        const preset = combinedPreset();

        if (Object.keys(preset).length === 0) {
            summary.textContent =
                entryType.value !== "" || domain.value !== ""
                    ? text("none_defined")
                    : text("choose");
            if (details) details.textContent = text("base_hint");
            return;
        }

        const sources = [];
        if (domainPreset) sources.push(text("source_domain"));
        if (entryTypePreset) sources.push(text("source_entry_type"));

        summary.textContent =
            `${text("preset_from")} ${sources.join(" + ")}: ` +
            `${text("enforce_phase")} ${optionLabel(fields.enforce_phase, preset.enforce_phase)}` +
            ` · ${text("severity")} ${optionLabel(fields.severity, preset.severity)}` +
            ` · ${text("min")} ${String(preset.min_count)}` +
            ` · ${text("max")} ${preset.max_count === null ? text("open") : String(preset.max_count)}` +
            ` · ${text("allow_multi")} ${preset.allow_multi ? text("yes") : text("no")}`;

        if (!details) return;
        const fieldNames = [
            "enforce_phase",
            "severity",
            "min_count",
            "max_count",
            "allow_multi",
        ];
        const has = (object, field) =>
            Boolean(object) && Object.prototype.hasOwnProperty.call(object, field);
        const fromDomain = fieldNames.filter((field) => has(domainPreset, field));
        const overridden = fieldNames.filter(
            (field) =>
                has(domainPreset, field) &&
                has(entryTypePreset, field) &&
                domainPreset[field] !== entryTypePreset[field],
        );
        const names = (list) => list.map((field) => text(field)).join(", ");

        if (fromDomain.length === 0 && entryTypePreset) {
            details.textContent = text("all_from_entry_type");
        } else if (overridden.length === 0) {
            details.textContent = `${text("base_from_domain")} ${names(fromDomain)}`;
        } else {
            details.textContent =
                `${text("base_from_domain")} ${names(fromDomain)}` +
                ` · ${text("overridden_by_entry_type")} ${names(overridden)}`;
        }
    };

    const applyPreset = (force) => {
        const preset = combinedPreset();
        if (Object.keys(preset).length === 0) {
            updateHint();
            return;
        }
        if (fields.enforce_phase && (force || fields.enforce_phase.value === "")) {
            fields.enforce_phase.value = preset.enforce_phase;
        }
        if (fields.severity && (force || fields.severity.value === "")) {
            fields.severity.value = preset.severity;
        }
        if (
            fields.min_count &&
            (force ||
                fields.min_count.value === "" ||
                fields.min_count.value === "1")
        ) {
            fields.min_count.value = String(preset.min_count);
        }
        if (fields.max_count && force) {
            fields.max_count.value =
                preset.max_count === null ? "" : String(preset.max_count);
        }
        if (fields.allow_multi && force) {
            fields.allow_multi.checked = Boolean(preset.allow_multi);
        }
        updateHint();
    };

    entryType.addEventListener("change", () => applyPreset(true));
    domain.addEventListener("change", () => applyPreset(true));

    const isEdit = entryType.dataset.requirementEditMode === "1";
    const hasOldInput = entryType.dataset.hasOldInput === "1";
    if (!isEdit && !hasOldInput && (entryType.value !== "" || domain.value !== "")) {
        applyPreset(false);
    } else {
        updateHint();
    }
}

export function initDialogForms(root) {
    if (!root) return;
    initExpenseCategory(root);
    initRequirementPresets(root);
}

// Alpine-Build-Switch (CSP Stufe 2, MVP-346): welcher Build hier landet,
// entscheidet die ENV ALPINE_CSP_BUILD über den Vite-Alias in vite.config.js
// (alpinejs → @alpinejs/csp). Der gesamte Code ist CSP-konform refactored
// (alle Komponenten via Alpine.data in resources/js/alpine/components.js,
// keine vom CSP-Parser abgelehnten Inline-Ausdrücke in Direktiven; Gate:
// CspNonceTest + Sweep). Produktiv umschalten erst nach Browser-Smoke-Test
// aller interaktiven Seiten (zusammen mit CSP_SCRIPT_NONCE, dann rebuild).
import Alpine from "alpinejs";
import { registerAlpineComponents } from "./alpine/components.js";
import { registerIdeaEditor } from "./idea-editor.js";
import { registerIdeaCanvas } from "./idea-canvas.js";
import { registerDesignEditor } from "./design-editor.js";
import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import { German } from "flatpickr/dist/l10n/de.js";
import weekSelect from "flatpickr/dist/plugins/weekSelect/weekSelect.js";
import { bindPushToggle } from "./push.js";
import { registerServiceWorker, bindInstallPrompt } from "./pwa.js";
import { initOfflineSync } from "./offline-sync.js";
import { __ } from "./i18n.js";
import "./sortable-tables.js";
import "./bulk-selection.js";
import "./inline-actions.js";
import "./global-search.js";
import "./header-autohide.js";
import "./help-drawer.js";
import "./quick-book.js";
import "./layout.js";
// facility-picker.js / tag-picker.js / work-schedule-form.js wurden in
// alpine/components.js als Alpine.data-Komponenten überführt (CSP-konform).

// PWA: Service Worker registrieren + Install-Button binden.
if (typeof window !== "undefined") {
    window.addEventListener("load", () => {
        registerServiceWorker();
        bindInstallPrompt();
        // Offline-Sync-Outbox (Feature 035, Phase 2): fängt markierte
        // Formulare nur im Offline-Fall ab und flusht bei Online/Fokus.
        initOfflineSync();
    });
}

window.Alpine = Alpine;
registerAlpineComponents(Alpine);
registerIdeaEditor(Alpine);
registerIdeaCanvas(Alpine);
registerDesignEditor(Alpine);
Alpine.start();

const htmlLang = (document.documentElement.lang || "de").toLowerCase();
const isGerman = htmlLang.startsWith("de");
const locale = isGerman ? German : "default";

// Sichtbares Eingabeformat je Oberflächensprache (deutsch TT.MM.JJJJ, englisch
// MM/TT/JJJJ usw.), während das tatsächlich abgeschickte (versteckte) Feld
// stets ISO `Y-m-d` bleibt — via Flatpickrs altInput/altFormat. Ohne altFormat
// zeigt das Feld sonst das ISO-Format, das viele als „englisch" empfinden.
// Bevorzugt das organisations-/benutzerseitig konfigurierte Format
// (window.__formats, im Layout gesetzt); fällt sonst auf die Sprach-Ableitung
// zurück. So zeigt der Datepicker dasselbe Format wie die serverseitige Anzeige.
const dateFormatByLang = { de: "d.m.Y", en: "m/d/Y", fr: "d/m/Y", it: "d/m/Y" };
const cfgFormats = (typeof window !== "undefined" && window.__formats) || {};
// PHP-Token → flatpickr-Token: identisch bis auf AM/PM (PHP "A" → flatpickr "K").
const toFlatpickrFormat = (fmt) => String(fmt || "").replace(/A/g, "K");
const dateAltFormat =
    cfgFormats.date || dateFormatByLang[htmlLang.slice(0, 2)] || "Y-m-d";
const timeAltFormat = toFlatpickrFormat(cfgFormats.time || "H:i");
// 12-Stunden-Format erkennen (PHP "h"=12h, "H"=24h; "K"=AM/PM nach Konvertierung).
const timeIs24h = !/[hK]/.test(timeAltFormat);
const datetimeAltFormat = dateAltFormat + " " + timeAltFormat;

const normalizeCompactTimeValue = (rawValue) => {
    const raw = String(rawValue || "").trim();
    if (!raw) return null;

    // Bereits im gewünschten Format: HH:MM
    const colonMatch = raw.match(/^(\d{1,2}):(\d{1,2})$/);
    if (colonMatch) {
        const hour = Number.parseInt(colonMatch[1], 10);
        const minute = Number.parseInt(colonMatch[2], 10);
        if (hour >= 0 && hour <= 23 && minute >= 0 && minute <= 59) {
            return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
        }
        return null;
    }

    // Komfort-Eingaben ohne Doppelpunkt: 7 -> 07:00, 930 -> 09:30, 1130 -> 11:30
    const compact = raw.replace(/\D+/g, "");
    if (!compact || compact.length > 4) return null;

    let hour = 0;
    let minute = 0;
    if (compact.length <= 2) {
        hour = Number.parseInt(compact, 10);
        minute = 0;
    } else if (compact.length === 3) {
        hour = Number.parseInt(compact.slice(0, 1), 10);
        minute = Number.parseInt(compact.slice(1), 10);
    } else {
        hour = Number.parseInt(compact.slice(0, 2), 10);
        minute = Number.parseInt(compact.slice(2), 10);
    }

    if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return null;
    return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
};

const applyNormalizedTimeInput = (el) => {
    if (!(el instanceof HTMLInputElement)) return;
    const isTimeField =
        el.type === "time" || el.dataset.wdOriginalType === "time";
    if (!isTimeField) return;

    const preferredRaw = el.dataset.wdRawTimeValue || "";
    const normalized = normalizeCompactTimeValue(preferredRaw || el.value);
    if (!normalized) return;

    const currentValue = String(el.value || "").trim();
    if (currentValue === normalized) {
        el.dataset.wdRawTimeValue = "";
        return;
    }

    if (el._flatpickr) {
        // Kein triggerChange hier, sonst kann ein change->normalize->setDate-Loop entstehen.
        el._flatpickr.setDate(normalized, false, "H:i");
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
    } else {
        el.value = normalized;
        el.dispatchEvent(new Event("input", { bubbles: true }));
        el.dispatchEvent(new Event("change", { bubbles: true }));
    }

    el.dataset.wdRawTimeValue = "";
};

const bindCompactTimeSupport = (el) => {
    if (!(el instanceof HTMLInputElement)) return;
    const isTimeField =
        el.type === "time" || el.dataset.wdOriginalType === "time";
    if (!isTimeField) return;
    if (el.dataset.wdCompactTimeBound === "1") return;

    el.dataset.wdCompactTimeBound = "1";
    el.addEventListener("input", () => {
        const raw = String(el.value || "").trim();
        if (/^\d{1,4}$/.test(raw) || /^\d{1,2}:\d{1,2}$/.test(raw)) {
            el.dataset.wdRawTimeValue = raw;
        }
    });
    el.addEventListener("blur", () => applyNormalizedTimeInput(el));
    el.addEventListener("change", () => applyNormalizedTimeInput(el));
    el.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") return;
        applyNormalizedTimeInput(el);
    });
};

flatpickr('input[type="date"]', {
    locale,
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: dateAltFormat,
    weekNumbers: true,
    allowInput: true,
    disableMobile: true,
});

flatpickr('input[type="datetime-local"]', {
    locale,
    enableTime: true,
    time_24hr: timeIs24h,
    minuteIncrement: 1,
    dateFormat: "Y-m-d\\TH:i",
    altInput: true,
    altFormat: datetimeAltFormat,
    weekNumbers: true,
    allowInput: true,
    disableMobile: true,
});

const prepareTimeInputForFlatpickr = (el) => {
    if (!(el instanceof HTMLInputElement)) return;
    if (el.dataset.wdOriginalType === "time") return;
    if (el.type !== "time") return;

    // Native <input type="time"> normalisiert Zeichenfolgen browserabhängig
    // (z. B. 1121 -> 11:01). Für konsistente Eingabe wird auf text umgestellt.
    el.dataset.wdOriginalType = "time";
    el.type = "text";
    el.inputMode = "numeric";
    if (!el.placeholder) el.placeholder = "HH:MM";
};

document.querySelectorAll('input[type="time"]').forEach((el) => {
    prepareTimeInputForFlatpickr(el);
    flatpickr(el, {
        locale,
        enableTime: true,
        noCalendar: true,
        time_24hr: true,
        minuteIncrement: 1,
        dateFormat: "H:i",
        allowInput: true,
        disableMobile: true,
    });
    bindCompactTimeSupport(el);
});

// Für dynamisch nachgeladene Felder (z. B. im Entry-Dialog)
window.__initFlatpickr = (el) => {
    if (!el || el._flatpickr) return;
    const originalType = el.dataset?.wdOriginalType || "";
    let t = el.getAttribute("type");
    if (t === "text" && originalType === "time") t = "time";
    const dialogEl = el.closest("dialog");

    const common = dialogEl
        ? {
              // Im Modal: Picker direkt am <dialog>-Element anhängen, nicht
              // im scrollbaren modal-box. So wird der Picker nicht geclippt
              // und das Top-Layer-Rendering des Dialogs lässt ihn korrekt
              // über dem Backdrop erscheinen.
              static: false,
              appendTo: dialogEl,
          }
        : {};
    if (t === "date") {
        flatpickr(el, {
            locale,
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: dateAltFormat,
            weekNumbers: true,
            allowInput: true,
            disableMobile: true,
            ...common,
        });
    } else if (t === "datetime-local") {
        flatpickr(el, {
            locale,
            enableTime: true,
            time_24hr: timeIs24h,
            minuteIncrement: 1,
            dateFormat: "Y-m-d\\TH:i",
            altInput: true,
            altFormat: datetimeAltFormat,
            weekNumbers: true,
            allowInput: true,
            disableMobile: true,
            ...common,
        });
    } else if (t === "time") {
        prepareTimeInputForFlatpickr(el);
        flatpickr(el, {
            locale,
            enableTime: true,
            noCalendar: true,
            time_24hr: true,
            minuteIncrement: 1,
            dateFormat: "H:i",
            allowInput: true,
            disableMobile: true,
            ...common,
        });
        bindCompactTimeSupport(el);
    }
};

// Wochen-Auswahl: type="week" -> type="text" + weekSelect-Plugin (lokalisiert)
document.querySelectorAll('input[type="week"]').forEach((el) => {
    const original = el.getAttribute("value") || ""; // z. B. "2026-W18"
    el.setAttribute("type", "text");

    let initialDate = null;
    const m = original.match(/^(\d{4})-W(\d{2})$/);
    if (m) {
        const year = parseInt(m[1], 10);
        const week = parseInt(m[2], 10);
        const simple = new Date(year, 0, 1 + (week - 1) * 7);
        const dow = simple.getDay();
        const isoMonday = new Date(simple);
        isoMonday.setDate(simple.getDate() - ((dow + 6) % 7));
        initialDate = isoMonday;
    }

    flatpickr(el, {
        locale,
        plugins: [new weekSelect()],
        dateFormat: "Y-\\WW",
        defaultDate: initialDate,
        weekNumbers: true,
        allowInput: true,
        disableMobile: true,
        onChange: (selectedDates, _dateStr, instance) => {
            if (!selectedDates.length) return;
            const d = selectedDates[0];
            const tmp = new Date(
                Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()),
            );
            const dayNum = tmp.getUTCDay() || 7;
            tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));
            const weekNo = Math.ceil(((tmp - yearStart) / 86400000 + 1) / 7);
            const isoYear = tmp.getUTCFullYear();
            const isoWeek = String(weekNo).padStart(2, "0");
            instance.input.value = `${isoYear}-W${isoWeek}`;
        },
    });

    if (initialDate) {
        el.value = original;
    }
});

// "An Bildschirm anpassen"-Toggle für die Wochenansicht (persistiert in localStorage)
(() => {
    const STORAGE_KEY = "weekTableFit";
    const apply = (on) => {
        document.documentElement.dataset.weekFit = on ? "1" : "0";
    };
    const stored = localStorage.getItem(STORAGE_KEY) === "1";
    apply(stored);
    document.querySelectorAll("input[data-week-fit]").forEach((cb) => {
        cb.checked = stored;
        cb.addEventListener("change", () => {
            apply(cb.checked);
            localStorage.setItem(STORAGE_KEY, cb.checked ? "1" : "0");
            // andere Checkboxen synchronisieren (falls mehrere im DOM)
            document
                .querySelectorAll("input[data-week-fit]")
                .forEach((other) => {
                    if (other !== cb) other.checked = cb.checked;
                });
        });
    });
})();

// Web-Push Toggle (Glocken-Icon im Layout, data-push-toggle)
if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => bindPushToggle());
    } else {
        bindPushToggle();
    }
}

// Recurrence-Mode-Toggle: delegierter Listener für [data-recurrence-select]-Selects
// (funktioniert sowohl für statisch geladene als auch per Dialog nachgeladene Formulare)
document.addEventListener("change", (e) => {
    const sel = e.target.closest("select[data-recurrence-select]");
    if (!sel) return;
    const form = sel.closest("[data-recurrence-form]");
    if (!form) return;
    const val = sel.value;
    form.querySelectorAll("[data-recurrence-show]").forEach((el) => {
        const modes = el.getAttribute("data-recurrence-show").split(" ");
        el.hidden = !modes.includes(val);
    });
});

// Generischer Dialog-Close-Handler:
// Schließt den nächsten umgebenden <dialog> für jedes [data-entry-modal-close]-Element.
// Ergänzt den entry-modal-spezifischen Handler weiter unten und greift für alle
// Standalone-<x-modal :embedded="false">-Dialoge (action-confirm, shift-dialog, …).
document.addEventListener("click", (event) => {
    const close = event.target.closest("[data-entry-modal-close]");
    if (!close) return;
    const dialog = close.closest("dialog");
    if (!dialog) return;
    event.preventDefault();
    if (typeof dialog.close === "function") {
        dialog.close();
    }
});

// Entry-Dialog: lädt Form/Detailansichten mit ?dialog=1 in ein globales <dialog>
(() => {
    if (typeof document === "undefined") return;

    let dialog = null;
    let dialogBody = null;

    const ensureDialog = () => {
        if (dialog && dialogBody) return { dialog, dialogBody };

        dialog = document.createElement("dialog");
        dialog.id = "entry-modal";
        dialog.className = "modal";
        dialog.innerHTML = `
            <div class="modal-box wd-modal-box wd-modal-box--standard p-0">
                <div id="entry-modal-body"></div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button aria-label="Close">close</button>
            </form>
        `;
        document.body.appendChild(dialog);
        dialogBody = dialog.querySelector("#entry-modal-body");

        dialog.addEventListener("click", (event) => {
            const close = event.target.closest("[data-entry-modal-close]");
            if (close) {
                event.preventDefault();
                dialog.close();
            }
        });

        return { dialog, dialogBody };
    };

    const withDialogParam = (url) => {
        const target = new URL(url, window.location.origin);
        target.searchParams.set("dialog", "1");
        return target.toString();
    };

    const applyRecurrenceToggle = (sel) => {
        const form = sel.closest("[data-recurrence-form]");
        if (!form) return;
        const val = sel.value;
        form.querySelectorAll("[data-recurrence-show]").forEach((el) => {
            const modes = el.getAttribute("data-recurrence-show").split(" ");
            el.hidden = !modes.includes(val);
        });
    };

    // Date-Range Linking: "Bis" darf nicht vor "Von" liegen. Wirkt sowohl
    // auf native Inputs (min/max-Constraint) als auch auf Flatpickr-
    // Instanzen (minDate/maxDate). Beim Setzen von "Von" wird "Bis" auch
    // korrigiert, falls es jetzt davor liegen würde.
    const bindRangeLinks = (root) => {
        if (!root) return;
        root.querySelectorAll("[data-range-link]").forEach((wrap) => {
            if (wrap.dataset.rangeLinkBound === "1") return;
            wrap.dataset.rangeLinkBound = "1";
            const fromInput = wrap.querySelector("[data-range-from]");
            const toInput = wrap.querySelector("[data-range-to]");
            if (!fromInput || !toInput) return;

            const syncFromChanged = () => {
                const v = fromInput.value;
                if (toInput._flatpickr) {
                    toInput._flatpickr.set("minDate", v || null);
                }
                if (v) toInput.setAttribute("min", v);
                else toInput.removeAttribute("min");
                if (v && toInput.value && toInput.value < v) {
                    if (toInput._flatpickr) {
                        toInput._flatpickr.setDate(v, true);
                    } else {
                        toInput.value = v;
                    }
                }
            };
            const syncToChanged = () => {
                const v = toInput.value;
                if (fromInput._flatpickr) {
                    fromInput._flatpickr.set("maxDate", v || null);
                }
                if (v) fromInput.setAttribute("max", v);
                else fromInput.removeAttribute("max");
            };

            fromInput.addEventListener("change", syncFromChanged);
            fromInput.addEventListener("input", syncFromChanged);
            toInput.addEventListener("change", syncToChanged);
            toInput.addEventListener("input", syncToChanged);

            // Initial sync, falls bereits Werte vorhanden sind oder
            // Flatpickr nach diesem Handler initialisiert wird.
            queueMicrotask(() => {
                syncFromChanged();
                syncToChanged();
            });
        });
    };
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () =>
            bindRangeLinks(document),
        );
    } else {
        bindRangeLinks(document);
    }

    // Abhängige Dropdowns: ein "Kind"-Select (z. B. Projekt) mit
    // `data-depends-on="<name des Eltern-Selects>"` (z. B. "customer_id") wird
    // nach dem Eltern-Wert (Kunde) gefiltert. Jede Kind-<option> trägt
    // `data-parent="<eltern-wert>"` (z. B. die customer_id des Projekts).
    //  - Eltern-Wechsel  → Kind-Optionen filtern; bleibt genau eine übrig, wird
    //                      sie automatisch gewählt; ungültige Auswahl wird geleert.
    //  - Kind-Wechsel    → setzt den (eindeutigen) Eltern-Wert aus data-parent.
    // Leeres data-parent = "passt zu jedem Elternwert" (z. B. Org-Projekt).
    const bindDependentSelects = (root) => {
        if (!root) return;
        root.querySelectorAll("select[data-depends-on]").forEach((child) => {
            if (child.dataset.dependentBound === "1") return;
            const scope = child.closest("form") || root;
            const parent = scope.querySelector(
                `select[name="${child.dataset.dependsOn}"]`,
            );
            if (!parent) return;
            child.dataset.dependentBound = "1";

            const apply = (autoSelectSingle) => {
                const pv = parent.value;
                const visible = [];
                child.querySelectorAll("option").forEach((opt) => {
                    if (opt.value === "") {
                        opt.hidden = false;
                        opt.disabled = false;
                        return;
                    }
                    const ov = opt.dataset.parent ?? "";
                    const match = pv === "" || ov === "" || ov === pv;
                    opt.hidden = !match;
                    opt.disabled = !match;
                    if (match) visible.push(opt);
                });
                const sel = child.selectedOptions[0];
                if (child.value && sel && sel.hidden) {
                    child.value = "";
                }
                if (
                    autoSelectSingle &&
                    !child.value &&
                    pv !== "" &&
                    visible.length === 1
                ) {
                    child.value = visible[0].value;
                }
            };

            parent.addEventListener("change", () => apply(true));

            child.addEventListener("change", () => {
                const opt = child.selectedOptions[0];
                const ov = opt && opt.dataset ? opt.dataset.parent : "";
                if (ov && parent.value !== ov) {
                    parent.value = ov;
                    parent.dispatchEvent(
                        new Event("change", { bubbles: true }),
                    );
                }
            });

            apply(true);
        });
    };
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () =>
            bindDependentSelects(document),
        );
    } else {
        bindDependentSelects(document);
    }

    const initDynamicFields = (root) => {
        if (!root) return;
        if (typeof window.__initFlatpickr === "function") {
            root.querySelectorAll(
                'input[type="date"], input[type="datetime-local"], input[type="time"]',
            ).forEach((el) => {
                window.__initFlatpickr(el);
            });
        }
        root.querySelectorAll("select[data-recurrence-select]").forEach(
            applyRecurrenceToggle,
        );
        bindDependentSelects(root);
        // Inline-<script>-Tags aus AJAX-Inhalten führt der Browser nicht aus.
        // Stattdessen binden wir hier die Listener für bekannte Form-Bausteine.
        bindRangeLinks(root);

        root.querySelectorAll("[data-time-mode-toggle]").forEach((toggle) => {
            const form = toggle.closest("form");
            if (!form || form.dataset.timeModeBound === "1") return;
            form.dataset.timeModeBound = "1";

            const radios = toggle.querySelectorAll("[data-time-mode-radio]");
            const panes = form.querySelectorAll("[data-time-mode-pane]");
            const hhmm = form.querySelector("[data-time-hhmm]");
            const hidden = form.querySelector("[data-time-minutes]");

            const toMinutes = (val) => {
                const parts = String(val || "").split(":");
                if (parts.length !== 2) return null;
                const h = parseInt(parts[0], 10);
                const m = parseInt(parts[1], 10);
                if (isNaN(h) || isNaN(m) || m < 0 || m > 59) return null;
                return h * 60 + m;
            };

            const applyMode = (mode) => {
                panes.forEach((p) => {
                    const active = p.dataset.timeModePane === mode;
                    p.hidden = !active;
                    // Inaktive Inputs nicht mitschicken: disabled = ignoriert
                    // beim Form-Submit, aber bleibt im DOM editierbar, falls
                    // der User wieder umschaltet.
                    p.querySelectorAll("input, select").forEach((inp) => {
                        inp.disabled = !active;
                    });
                });
            };

            radios.forEach((r) => {
                r.addEventListener("change", () => {
                    if (r.checked) applyMode(r.dataset.target);
                });
            });
            const initial = Array.from(radios).find((r) => r.checked);
            applyMode(initial?.dataset.target || "duration");

            hhmm?.addEventListener("input", () => {
                const min = toMinutes(hhmm.value);
                if (hidden) hidden.value = min !== null ? String(min) : "";
            });

            form.addEventListener("submit", () => {
                if (hhmm && !hhmm.disabled && hidden) {
                    const min = toMinutes(hhmm.value);
                    if (min !== null) hidden.value = String(min);
                }
            });
        });

        root.querySelectorAll("[data-filter-list]").forEach((list) => {
            const scope = list.closest("[data-filter-scope]") || root;
            const search = scope.querySelector("[data-filter-search]");
            const select = scope.querySelector("[data-filter-customer]");
            const empty = scope.querySelector("[data-filter-empty]");
            const apply = () => {
                const q = (search?.value || "").trim().toLowerCase();
                const cust = select?.value || "";
                let visibleCards = 0;
                const cards = list.querySelectorAll("[data-card]");
                if (cards.length > 0) {
                    // Karten-Modus: Parent-Header und Subprojekte separat
                    // prüfen. Wenn ein Subprojekt matched, bleibt der Header
                    // als Kontext-Zeile sichtbar — auch wenn der Header selbst
                    // weder Such- noch Kundenfilter matched. Damit verliert
                    // man bei gefilterten Subprojekten nicht die Kunden-/
                    // Eltern-Zuordnung.
                    const matches = (item) => {
                        const matchText =
                            q === "" || item.dataset.haystack.includes(q);
                        const matchCust =
                            cust === "" || item.dataset.customer === cust;
                        return matchText && matchCust;
                    };
                    cards.forEach((card) => {
                        const parentLink = card.querySelector(
                            ":scope > [data-haystack]",
                        );
                        const childItems = card.querySelectorAll(
                            ":scope > ul > li[data-haystack]",
                        );

                        let visibleChildren = 0;
                        childItems.forEach((item) => {
                            const show = matches(item);
                            item.hidden = !show;
                            if (show) visibleChildren++;
                        });

                        let parentVisible = false;
                        if (parentLink) {
                            parentVisible =
                                matches(parentLink) || visibleChildren > 0;
                            parentLink.hidden = !parentVisible;
                            // Wenn der Header nur als Kontext sichtbar ist,
                            // dezent ausgrauen, damit erkennbar bleibt, dass
                            // der Treffer eines der Sub-Projekte ist.
                            const isContextOnly =
                                parentVisible &&
                                !matches(parentLink) &&
                                visibleChildren > 0;
                            parentLink.classList.toggle(
                                "opacity-60",
                                isContextOnly,
                            );
                        }

                        const visibleInCard =
                            (parentVisible ? 1 : 0) + visibleChildren;
                        card.hidden = visibleInCard === 0;
                        if (visibleInCard > 0) visibleCards++;
                    });
                } else {
                    list.querySelectorAll("[data-haystack]").forEach((item) => {
                        const matchText =
                            q === "" || item.dataset.haystack.includes(q);
                        const matchCust =
                            cust === "" || item.dataset.customer === cust;
                        const show = matchText && matchCust;
                        item.hidden = !show;
                        if (show) visibleCards++;
                    });
                }
                if (empty) empty.classList.toggle("hidden", visibleCards > 0);
            };
            search?.addEventListener("input", apply);
            select?.addEventListener("change", apply);
        });
    };

    const bindDialogForms = (root) => {
        if (!root) return;

        root.querySelectorAll("form[data-entry-form]").forEach((form) => {
            if (form.dataset.entryFormBound === "1") return;
            form.dataset.entryFormBound = "1";

            // "Aktiv"-Toggle im Header sperrt/entsperrt den Dialog-Body.
            const activeToggle = form.querySelector(
                'input[type="checkbox"][data-dialog-active-toggle]',
            );
            const dialogBody = form.querySelector(".wd-dialog__body");
            if (activeToggle && dialogBody) {
                const applyLock = () => {
                    const locked = !activeToggle.checked;
                    dialogBody.toggleAttribute("inert", locked);
                    dialogBody.classList.toggle(
                        "wd-dialog__body--locked",
                        locked,
                    );
                };
                activeToggle.addEventListener("change", applyLock);
                applyLock();
            }

            form.addEventListener("submit", async (event) => {
                event.preventDefault();

                const action =
                    form.getAttribute("action") || window.location.href;
                const methodRaw = (
                    form.getAttribute("method") || "POST"
                ).toUpperCase();
                const method = methodRaw === "GET" ? "GET" : "POST";
                const formData = new FormData(form);
                const submitButton = form.querySelector(
                    'button[type="submit"]',
                );
                if (submitButton) submitButton.disabled = true;

                try {
                    const response = await fetch(action, {
                        method,
                        body: formData,
                        headers: {
                            "X-Entry-Dialog": "1",
                            "X-Requested-With": "XMLHttpRequest",
                            Accept: "application/json",
                        },
                    });

                    if (response.ok) {
                        const contentType =
                            response.headers.get("content-type") || "";
                        if (contentType.includes("application/json")) {
                            const payload = await response.json();
                            if (payload.redirect) {
                                window.location.href = payload.redirect;
                                return;
                            }
                        }
                        window.location.reload();
                        return;
                    }

                    if (response.status === 422) {
                        const payload = await response.json().catch(() => null);
                        const firstError =
                            payload && payload.errors
                                ? Object.values(payload.errors).flat()[0]
                                : null;
                        const checkInputMsg = __("js.dialog.check_input");
                        if (typeof window.notifyAction === "function") {
                            window.notifyAction({
                                tone: "warning",
                                message: firstError || checkInputMsg,
                            });
                        }
                        return;
                    }

                    // Fallback: Falls ein Controller HTML statt JSON zurückgibt
                    const html = await response.text();
                    if (dialogBody) {
                        dialogBody.innerHTML = html;
                        initDynamicFields(dialogBody);
                        bindDialogForms(dialogBody);
                    }
                } catch (_error) {
                    const saveFailedMsg = __("js.dialog.save_failed");
                    if (typeof window.notifyAction === "function") {
                        window.notifyAction({
                            tone: "error",
                            message: saveFailedMsg,
                        });
                    }
                } finally {
                    if (submitButton) submitButton.disabled = false;
                }
            });
        });
    };

    const openEntryDialog = async (rawUrl) => {
        const { dialog: modal, dialogBody: body } = ensureDialog();
        const url = withDialogParam(rawUrl);

        const loadingMsg = __("js.dialog.loading");
        const loadFailedMsg = __("js.dialog.load_failed");
        body.innerHTML = `
            <div class="flex flex-col items-center justify-center gap-3 p-12 text-base-content/70">
                <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
                <span class="text-sm">${loadingMsg}</span>
            </div>
        `;
        if (typeof modal.showModal === "function") {
            modal.showModal();
        }

        const renderLoadError = () => {
            body.innerHTML = `
                <div class="p-6 space-y-3">
                    <p class="text-sm text-error">${loadFailedMsg}</p>
                    <a href="${rawUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-ghost">${__("js.dialog.open_in_new_tab")}</a>
                </div>
            `;
        };

        // Modus-Konflikt (HTTP 409 aus EnsureNewSystemAccess bzw.
        // EnsureLegacyAccess): der aktive Arbeitsmodus passt nicht zum Bereich
        // des angeforderten Dialogs. Statt eines stillen Konsolenfehlers die
        // (serverseitig lokalisierte) Meldung zeigen und einen Direkt-Wechsel in
        // den jeweils benötigten Modus anbieten. `targetMode` kommt aus der
        // 409-Antwort ('new' = Dialog gehört zum neuen System, 'legacy' = zum
        // Legacy-Bereich).
        const renderModeConflict = (message, targetMode) => {
            const mode = targetMode === "legacy" ? "legacy" : "new";
            const label =
                mode === "legacy"
                    ? __("js.dialog.switch_to_legacy")
                    : __("js.dialog.switch_to_new");
            body.innerHTML = `
                <div class="p-6 space-y-3">
                    <p class="text-sm text-warning">${message}</p>
                    <button type="button" data-mode-switch-retry class="btn btn-sm btn-primary">${label}</button>
                </div>
            `;
            const retryBtn = body.querySelector("[data-mode-switch-retry]");
            if (!retryBtn) return;
            retryBtn.addEventListener("click", async () => {
                retryBtn.disabled = true;
                const csrf =
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content") ?? "";
                try {
                    const res = await fetch(`/mode/${mode}`, {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": csrf,
                            "X-Requested-With": "XMLHttpRequest",
                            Accept: "application/json",
                        },
                        credentials: "same-origin",
                    });
                    if (!res.ok && res.status !== 302) {
                        retryBtn.disabled = false;
                        return;
                    }
                } catch (_e) {
                    retryBtn.disabled = false;
                    return;
                }
                // Modus ist jetzt umgeschaltet – Dialog frisch laden.
                openEntryDialog(rawUrl);
            });
        };

        try {
            const response = await fetch(url, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            const html = await response.text();

            if (!response.ok) {
                if (response.status === 409) {
                    let message = "";
                    let targetMode = "new";
                    try {
                        const parsed = JSON.parse(html);
                        message = parsed?.message ?? "";
                        targetMode = parsed?.target_mode ?? "new";
                    } catch (_e) {
                        message = "";
                    }
                    if (message) {
                        renderModeConflict(message, targetMode);
                        return;
                    }
                }
                renderLoadError();
                return;
            }

            body.innerHTML = html;
            initDynamicFields(body);
            bindDialogForms(body);
        } catch (_error) {
            renderLoadError();
        }
    };

    const findModalTrigger = (event) => {
        if (event.target instanceof Element) {
            return event.target.closest("a[data-entry-modal-trigger]");
        }

        if (typeof event.composedPath === "function") {
            const path = event.composedPath();
            for (const node of path) {
                if (
                    node instanceof Element &&
                    node.matches("a[data-entry-modal-trigger]")
                ) {
                    return node;
                }
            }
        }

        return null;
    };

    document.addEventListener(
        "click",
        (event) => {
            if (event.defaultPrevented) return;
            if (event.button !== 0) return;
            if (
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            )
                return;

            const trigger = findModalTrigger(event);
            if (!trigger) return;

            const href = trigger.getAttribute("href");
            if (!href) return;

            event.preventDefault();
            openEntryDialog(href);
        },
        true,
    );

    // Delegated geocoding handler for travel-log inputs.
    // Works both in static pages and within AJAX-injected dialog content.
    const geocodeAddressInput = async (input) => {
        const url = document
            .querySelector('meta[name="geocode-url"]')
            ?.getAttribute("content");
        if (!url) return;
        const csrf =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") ?? "";
        const q = input.value.trim();
        if (q.length < 3) return;
        input.classList.add("opacity-70");
        try {
            const res = await fetch(url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrf,
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                body: JSON.stringify({ query: q }),
            });
            if (!res.ok) {
                input.dataset.geocode = "miss";
                return;
            }
            const data = await res.json();
            input.dataset.geocode = "hit";
            input.dataset.lat = data.lat;
            input.dataset.lng = data.lng;
            input.title = data.display_name || `${data.lat}, ${data.lng}`;
        } catch (_error) {
            input.dataset.geocode = "error";
        } finally {
            input.classList.remove("opacity-70");
        }
    };

    document.addEventListener(
        "blur",
        (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) return;
            if (!target.matches("input[data-travel-geocode]")) return;
            geocodeAddressInput(target);
        },
        true,
    );
})();

import Alpine from "alpinejs";
import flatpickr from "flatpickr";
import SignaturePad from "signature_pad";
import "flatpickr/dist/flatpickr.min.css";
import { German } from "flatpickr/dist/l10n/de.js";
import weekSelect from "flatpickr/dist/plugins/weekSelect/weekSelect.js";
import { bindPushToggle } from "./push.js";
import { __ } from "./i18n.js";
import "./sortable-tables.js";

window.Alpine = Alpine;
window.SignaturePad = SignaturePad;
Alpine.start();

const htmlLang = (document.documentElement.lang || "de").toLowerCase();
const locale = htmlLang.startsWith("de") ? German : "default";

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
    const isTimeField = el.type === "time" || el.dataset.wdOriginalType === "time";
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
    const isTimeField = el.type === "time" || el.dataset.wdOriginalType === "time";
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
    weekNumbers: true,
    allowInput: true,
    disableMobile: true,
});

flatpickr('input[type="datetime-local"]', {
    locale,
    enableTime: true,
    time_24hr: true,
    minuteIncrement: 1,
    dateFormat: "Y-m-d\\TH:i",
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
                            // In Dialogen Picker direkt am Feld rendern, damit keine
                            // fehlerhaften Offsets zur Modal-Box entstehen.
                            static: true,
                    }
        : {};
    if (t === "date") {
        flatpickr(el, {
            locale,
            dateFormat: "Y-m-d",
            weekNumbers: true,
            allowInput: true,
            disableMobile: true,
            ...common,
        });
    } else if (t === "datetime-local") {
        flatpickr(el, {
            locale,
            enableTime: true,
            time_24hr: true,
            minuteIncrement: 1,
            dateFormat: "Y-m-d\\TH:i",
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
            <div class="modal-box w-11/12 max-w-4xl p-0">
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
    };

    const bindDialogForms = (root) => {
        if (!root) return;

        root.querySelectorAll("form[data-entry-form]").forEach((form) => {
            if (form.dataset.entryFormBound === "1") return;
            form.dataset.entryFormBound = "1";

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
                        } else {
                            // eslint-disable-next-line no-alert
                            window.alert(firstError || checkInputMsg);
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
                    } else {
                        // eslint-disable-next-line no-alert
                        window.alert(saveFailedMsg);
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
        body.innerHTML = `<div class="p-6 text-sm text-base-content/70">${loadingMsg}</div>`;
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

        try {
            const response = await fetch(url, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            const html = await response.text();

            if (!response.ok) {
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

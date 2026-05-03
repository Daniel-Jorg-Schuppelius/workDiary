import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import { German } from "flatpickr/dist/l10n/de.js";
import weekSelect from "flatpickr/dist/plugins/weekSelect/weekSelect.js";
import { bindPushToggle } from "./push.js";

const htmlLang = (document.documentElement.lang || "de").toLowerCase();
const locale = htmlLang.startsWith("de") ? German : "default";

flatpickr('input[type="date"]', {
    locale,
    dateFormat: "Y-m-d",
    weekNumbers: true,
    allowInput: true,
});

flatpickr('input[type="datetime-local"]', {
    locale,
    enableTime: true,
    time_24hr: true,
    dateFormat: "Y-m-d\\TH:i",
    weekNumbers: true,
    allowInput: true,
});

flatpickr('input[type="time"]', {
    locale,
    enableTime: true,
    noCalendar: true,
    time_24hr: true,
    dateFormat: "H:i",
    allowInput: true,
});

// Für dynamisch nachgeladene Felder (z. B. im Entry-Dialog)
window.__initFlatpickr = (el) => {
    if (!el || el._flatpickr) return;
    const t = el.getAttribute("type");
    const dialogEl = el.closest("dialog");

    // Im dialog-Kontext: appendTo in den Dialog (Top-Layer), damit der Kalender
    // nicht vom modal-backdrop verdeckt wird. onOpen setzt position:fixed + z-index
    // via getBoundingClientRect, da DaisyUI's dialog kein transform/filter hat.
    const repositionCalendar = (fp) => {
        const cal = fp.calendarContainer;
        const r = fp.input.getBoundingClientRect();
        cal.style.position = "fixed";
        cal.style.zIndex = "9999";
        // Platz unter dem Eingabefeld, nach oben wechseln wenn zu wenig Platz
        const spaceBelow = window.innerHeight - r.bottom;
        const calH = cal.offsetHeight || 300;
        if (spaceBelow < calH && r.top > calH) {
            cal.style.top = `${r.top - calH - 2}px`;
        } else {
            cal.style.top = `${r.bottom + 2}px`;
        }
        cal.style.left = `${Math.min(r.left, window.innerWidth - (cal.offsetWidth || 300) - 8)}px`;
        cal.style.right = "auto";
    };

    const common = dialogEl
        ? {
              appendTo: dialogEl,
              static: false,
              onOpen: [repositionCalendar],
              onReady: [repositionCalendar],
          }
        : {};
    if (t === "date") {
        flatpickr(el, {
            locale,
            dateFormat: "Y-m-d",
            weekNumbers: true,
            allowInput: true,
            ...common,
        });
    } else if (t === "datetime-local") {
        flatpickr(el, {
            locale,
            enableTime: true,
            time_24hr: true,
            dateFormat: "Y-m-d\\TH:i",
            weekNumbers: true,
            allowInput: true,
            ...common,
        });
    } else if (t === "time") {
        flatpickr(el, {
            locale,
            enableTime: true,
            noCalendar: true,
            time_24hr: true,
            dateFormat: "H:i",
            allowInput: true,
            ...common,
        });
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

    const initDynamicFields = (root) => {
        if (!root || typeof window.__initFlatpickr !== "function") return;
        root.querySelectorAll(
            'input[type="date"], input[type="datetime-local"], input[type="time"]',
        ).forEach((el) => {
            window.__initFlatpickr(el);
        });
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
                        window.alert(firstError || "Bitte Eingaben prüfen.");
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
                    window.alert("Dialog konnte nicht gespeichert werden.");
                } finally {
                    if (submitButton) submitButton.disabled = false;
                }
            });
        });
    };

    const openEntryDialog = async (rawUrl) => {
        const { dialog: modal, dialogBody: body } = ensureDialog();
        const url = withDialogParam(rawUrl);

        body.innerHTML =
            '<div class="p-6 text-sm text-base-content/70">Lade…</div>';
        if (typeof modal.showModal === "function") {
            modal.showModal();
        }

        try {
            const response = await fetch(url, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });
            if (!response.ok) {
                window.location.href = rawUrl;
                return;
            }

            const html = await response.text();
            body.innerHTML = html;
            initDynamicFields(body);
            bindDialogForms(body);
        } catch (_error) {
            window.location.href = rawUrl;
        }
    };

    document.addEventListener("click", (event) => {
        const trigger = event.target.closest("a[data-entry-modal-trigger]");
        if (!trigger) return;

        const href = trigger.getAttribute("href");
        if (!href) return;

        event.preventDefault();
        openEntryDialog(href);
    });
})();

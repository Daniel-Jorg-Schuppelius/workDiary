import flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.min.css";
import { German } from "flatpickr/dist/l10n/de.js";
import weekSelect from "flatpickr/dist/plugins/weekSelect/weekSelect.js";

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

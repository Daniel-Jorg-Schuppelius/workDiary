/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : entry-bar.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

import { __ } from "../i18n.js";

/**
 * Eingabeleiste auf „Heute" (Toggl-artig): Beschreibung + durchsuchbare
 * Projekt-Combobox + Timer-Start bzw. Manuell-Buchung (Dauer oder Von/Bis).
 *
 * CSP-Build: Direktiven referenzieren ausschließlich Getter/Methoden dieser
 * Komponente; die Konfiguration kommt als JSON über data-config. Der
 * Formular-Action wechselt per Getter zwischen Stoppuhr-Start (Timer) und
 * dem Entry-Bar-Store (Manuell); ohne JS gilt das statische action-Attribut
 * (Manuell-Modus).
 */
export function registerEntryBar(Alpine) {
    Alpine.data("entryBar", () => {
        const optionsCache = new Map();

        const toMinutes = (val) => {
            const parts = String(val || "").split(":");
            if (parts.length !== 2) return null;
            const h = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10);
            if (isNaN(h) || isNaN(m) || m < 0 || m > 59) return null;
            return h * 60 + m;
        };

        return {
            projects: [],
            optionsUrl: "",
            startUrl: "",
            storeUrl: "",
            isToday: true,
            mode: "timer",
            timeMode: "duration",
            moreOpen: false,
            query: "",
            open: false,
            highlight: 0,
            selectedId: "",
            selectedName: "",
            taskId: "",
            diaryEntryId: "",
            pendingTaskId: "",
            pendingDiaryEntryId: "",
            tasks: [],
            diaryEntries: [],
            hhmm: "",
            minutes: "",

            init() {
                const cfg = JSON.parse(this.$el.dataset.config || "{}");
                this.projects = (cfg.projects ?? []).map((p) => ({
                    id: String(p.id ?? ""),
                    name: String(p.name ?? ""),
                    customer: p.customer ? String(p.customer) : "",
                    recent: p.recent === true,
                }));
                this.optionsUrl = String(cfg.optionsUrl ?? "");
                this.startUrl = String(cfg.startUrl ?? "");
                this.storeUrl = String(cfg.storeUrl ?? "");
                this.isToday = cfg.isToday !== false;

                const storedMode = localStorage.getItem("wd.entrybar.mode");
                if (storedMode === "timer" || storedMode === "manual") {
                    this.mode = storedMode;
                }
                const storedTime = localStorage.getItem("wd.entrybar.timeMode");
                if (storedTime === "duration" || storedTime === "range") {
                    this.timeMode = storedTime;
                }
                if (!this.isToday) {
                    // Vergangene Tage: nur Manuell-Buchung sinnvoll.
                    this.mode = "manual";
                }

                // Nach Validierungsfehler: old()-Werte (Sqids/Minuten) wieder
                // anwenden; Task/Auftrag erst nach dem Options-Fetch.
                const oldMinutes = parseInt(cfg.minutes ?? "", 10);
                if (!isNaN(oldMinutes) && oldMinutes > 0) {
                    this.minutes = String(oldMinutes);
                    const p = (n) => String(n).padStart(2, "0");
                    this.hhmm = Math.floor(oldMinutes / 60) + ":" + p(oldMinutes % 60);
                }
                this.pendingTaskId = cfg.taskId ? String(cfg.taskId) : "";
                this.pendingDiaryEntryId = cfg.diaryEntryId ? String(cfg.diaryEntryId) : "";
                const initial = cfg.selectedId ? String(cfg.selectedId) : "";
                if (initial) {
                    const p = this.projects.find((x) => x.id === initial);
                    if (p) this.choose(p);
                }
            },

            // ── Modus ────────────────────────────────────────────────────────
            get isTimer() {
                return this.isToday && this.mode === "timer";
            },
            get isManual() {
                return !this.isTimer;
            },
            get isDuration() {
                return this.timeMode === "duration";
            },
            get isRange() {
                return this.timeMode === "range";
            },
            get formAction() {
                return this.isTimer ? this.startUrl : this.storeUrl;
            },
            // Dreifach-Segment Timer | Dauer | Von/Bis: genau EIN Button ist
            // aktiv (Primärfarbe); Dauer/Von-Bis schalten zugleich auf Manuell.
            get timerBtnClass() {
                return this.isTimer ? "btn-primary" : "";
            },
            get durationBtnClass() {
                return this.isManual && this.isDuration ? "btn-primary" : "";
            },
            get rangeBtnClass() {
                return this.isManual && this.isRange ? "btn-primary" : "";
            },
            setModeTimer() {
                if (!this.isToday) return;
                this.mode = "timer";
                localStorage.setItem("wd.entrybar.mode", "timer");
            },
            setDuration() {
                this.mode = "manual";
                this.timeMode = "duration";
                localStorage.setItem("wd.entrybar.mode", "manual");
                localStorage.setItem("wd.entrybar.timeMode", "duration");
            },
            setRange() {
                this.mode = "manual";
                this.timeMode = "range";
                localStorage.setItem("wd.entrybar.mode", "manual");
                localStorage.setItem("wd.entrybar.timeMode", "range");
            },

            // Inaktive Eingaben disabled halten → sie fehlen im Submit
            // (gleiches Prinzip wie der data-time-mode-toggle im Dialog).
            get manualDisabled() {
                return !this.isManual;
            },
            get durationDisabled() {
                return !this.isManual || !this.isDuration;
            },
            get rangeDisabled() {
                return !this.isManual || !this.isRange;
            },
            // Sichtbarkeit der Inline-Zeitfelder (einzeilige Leiste).
            get showDurationPane() {
                return this.isManual && this.isDuration;
            },
            get showRangePane() {
                return this.isManual && this.isRange;
            },

            // ── Projekt-Combobox ────────────────────────────────────────────
            // Ohne Suchtext: zwei Gruppen — „Zuletzt verwendet" (Top 10 der
            // zuletzt bebuchten Projekte) und „Weitere Projekte". Mit Suchtext:
            // flache Trefferliste. `filtered` bleibt die flache Gesamtliste
            // für Tastatur-Navigation und Menü-Sichtbarkeit.
            get queryActive() {
                const q = this.query.trim().toLowerCase();
                return q !== "" && q !== this.selectedName.toLowerCase();
            },
            get primaryFiltered() {
                if (this.queryActive) {
                    const q = this.query.trim().toLowerCase();
                    // p.customer ist bei internen Projekten null (kein Kunde).
                    return this.projects
                        .filter(
                            (p) =>
                                p.name.toLowerCase().includes(q) ||
                                (p.customer || "").toLowerCase().includes(q),
                        )
                        .slice(0, 10);
                }
                return this.projects.filter((p) => p.recent).slice(0, 10);
            },
            get otherFiltered() {
                if (this.queryActive) {
                    return [];
                }
                return this.projects.filter((p) => !p.recent).slice(0, 10);
            },
            get filtered() {
                return this.primaryFiltered.concat(this.otherFiltered);
            },
            get showRecentLabel() {
                return !this.queryActive && this.primaryFiltered.length > 0;
            },
            get showOtherLabel() {
                return !this.queryActive && this.primaryFiltered.length > 0 && this.otherFiltered.length > 0;
            },
            optionClassOther(idx) {
                return this.optionClass(idx + this.primaryFiltered.length);
            },
            setHighlightOther(idx) {
                this.highlight = idx + this.primaryFiltered.length;
            },
            get showMenu() {
                return this.open && this.filtered.length > 0;
            },
            get showEmpty() {
                return this.open && this.filtered.length === 0;
            },
            get hasProject() {
                return this.selectedId !== "";
            },
            get hasTasks() {
                return this.tasks.length > 0;
            },
            get hasDiary() {
                return this.diaryEntries.length > 0;
            },
            get hasSecondary() {
                return this.hasTasks || this.hasDiary;
            },
            get noSecondary() {
                return this.hasProject && !this.hasTasks && !this.hasDiary;
            },
            optionClass(idx) {
                // daisyUI 5: Markierung heißt menu-active („active" ist wirkungslos).
                return idx === this.highlight ? "menu-active" : "";
            },
            setHighlight(idx) {
                this.highlight = idx;
            },
            openMenu() {
                this.open = true;
            },
            closeMenu() {
                this.open = false;
            },
            onInput() {
                this.open = true;
                this.highlight = 0;
                // Freitext ≠ gewähltes Projekt → Auswahl verwerfen.
                if (this.selectedId && this.query !== this.selectedName) {
                    this.selectedId = "";
                    this.selectedName = "";
                    this.clearOptions();
                }
            },
            move(dir) {
                const len = this.filtered.length;
                if (!len) {
                    this.highlight = 0;
                    return;
                }
                this.open = true;
                this.highlight = (this.highlight + dir + len) % len;
            },
            enterPressed() {
                const list = this.filtered;
                if (this.open && list.length && this.highlight >= 0 && this.highlight < list.length) {
                    this.choose(list[this.highlight]);
                }
            },
            choose(project) {
                if (!project) return;
                this.selectedId = project.id;
                this.selectedName = project.name;
                this.query = project.name;
                this.open = false;
                this.highlight = 0;
                this.fetchOptions(project.id);
            },

            // ── Projektabhängige Optionen (Aufgabe/Auftrag) ─────────────────
            clearOptions() {
                this.tasks = [];
                this.diaryEntries = [];
                this.taskId = "";
                this.diaryEntryId = "";
            },
            applyOptions(data) {
                this.tasks = Array.isArray(data.tasks) ? data.tasks : [];
                this.diaryEntries = Array.isArray(data.diaryEntries) ? data.diaryEntries : [];
                // x-model erst anwenden, wenn die per x-for erzeugten Optionen
                // existieren (sonst greift die Auswahl ins Leere).
                this.$nextTick(() => {
                    if (this.pendingTaskId && this.tasks.some((t) => t.id === this.pendingTaskId)) {
                        this.taskId = this.pendingTaskId;
                    }
                    if (this.pendingDiaryEntryId && this.diaryEntries.some((d) => d.id === this.pendingDiaryEntryId)) {
                        this.diaryEntryId = this.pendingDiaryEntryId;
                    }
                    this.pendingTaskId = "";
                    this.pendingDiaryEntryId = "";
                });
            },
            fetchOptions(projectId) {
                this.taskId = "";
                this.diaryEntryId = "";
                if (optionsCache.has(projectId)) {
                    this.applyOptions(optionsCache.get(projectId));
                    return;
                }
                fetch(this.optionsUrl.replace("__ID__", encodeURIComponent(projectId)), {
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                })
                    .then((r) => {
                        if (!r.ok) throw new Error(String(r.status));
                        return r.json();
                    })
                    .then((data) => {
                        optionsCache.set(projectId, data);
                        // Nur anwenden, wenn das Projekt noch gewählt ist.
                        if (this.selectedId === projectId) {
                            this.applyOptions(data);
                        }
                    })
                    .catch(() => {
                        this.clearOptions();
                        const notify = /** @type {{ notifyAction?: (opts: { tone?: string, message: string }) => void }} */ (
                            /** @type {unknown} */ (window)
                        ).notifyAction;
                        if (typeof notify === "function") {
                            notify({ tone: "warning", message: __("js.entry_bar.options_failed") });
                        }
                    });
            },
            toggleMore() {
                this.moreOpen = !this.moreOpen;
            },
            get moreChevron() {
                return this.moreOpen ? "expand_less" : "expand_more";
            },

            // ── Dauer (HH:MM → Minuten) ─────────────────────────────────────
            onHhmmInput() {
                const min = toMinutes(this.hhmm);
                this.minutes = min !== null ? String(min) : "";
            },
        };
    });
}

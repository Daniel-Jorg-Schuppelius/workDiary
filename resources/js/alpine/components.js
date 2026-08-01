/*
 * Zentrale Alpine-Komponenten (Alpine.data). Voraussetzung für den CSP-Build
 * (@alpinejs/csp): x-data referenziert NUR registrierte Namen, und Direktiven
 * dürfen ausschließlich Property-/Getter-Zugriffe oder Methodenaufrufe enthalten
 * (keine Inline-Ausdrücke, keine Operatoren/Ternaries). Funktioniert auch im
 * Standard-Build, daher migrierbar OHNE Build-Wechsel.
 */
import { clearHtml, setHtml, trustedServerHtml } from "../lib/html.js";

export function registerAlpineComponents(Alpine) {
    // Zwei-Faktor-Login: Umschalten zwischen TOTP-Code und Recovery-Code.
    Alpine.data("twoFactorChallenge", () => ({
        recovery: false,
        get authMode() {
            return !this.recovery;
        },
        toggle() {
            this.recovery = !this.recovery;
        },
    }));

    // Laufende Uhr (Stoppuhr/Anwesenheit) – zählt ab started_at hoch.
    Alpine.data("stopwatch", (startedIso) => ({
        s: 0,
        init() {
            const started = new Date(startedIso).getTime();
            this.s = Math.max(0, Math.floor((Date.now() - started) / 1000));
            setInterval(() => {
                this.s++;
            }, 1000);
        },
        get display() {
            const p = (n) => String(n).padStart(2, "0");
            return p(Math.floor(this.s / 3600)) + ":" + p(Math.floor((this.s % 3600) / 60)) + ":" + p(this.s % 60);
        },
        get displayShort() {
            const p = (n) => String(n).padStart(2, "0");
            return p(Math.floor(this.s / 3600)) + ":" + p(Math.floor((this.s % 3600) / 60));
        },
    }));

    // Generische Tab-Umschaltung (CSP-konform via Methoden/Getter statt
    // Inline-Ausdrücken). Optionen per data-Attribut am x-data-Element:
    //   data-tab-persist="<key>"  → localStorage-Persistenz (z. B. Dashboard)
    //   data-tab-url-sync         → ?tab=-Query/Hash-Sync via history.replaceState
    //   data-tab-allowed="a,b,c"  → erlaubte Werte; Fallback = erster Eintrag
    Alpine.data("tabs", (initial) => ({
        tab: initial,
        persistKey: null,
        urlSync: false,
        allowed: null,
        init() {
            const d = this.$el.dataset;
            this.persistKey = d.tabPersist || null;
            this.urlSync = d.tabUrlSync !== undefined;
            this.allowed = d.tabAllowed ? d.tabAllowed.split(",") : null;
            const ok = (v) => Boolean(v) && (!this.allowed || this.allowed.includes(v));
            if (this.persistKey) {
                const stored = localStorage.getItem(this.persistKey);
                if (ok(stored)) {
                    this.tab = stored;
                }
            }
            if (this.urlSync) {
                const fromQuery = new URLSearchParams(window.location.search).get("tab");
                const fromHash = window.location.hash.replace("#", "");
                this.tab = ok(fromQuery)
                    ? fromQuery
                    : ok(this.tab)
                      ? this.tab
                      : ok(fromHash)
                        ? fromHash
                        : this.allowed
                          ? this.allowed[0]
                          : this.tab;
            }
            this.$nextTick(() => this.syncTabFooters());
        },
        // Stehende Footer-Panels (x-pagination standing) mit data-tab-footer
        // nur beim zugehörigen Tab zeigen — Tabs sind hier clientseitig.
        syncTabFooters() {
            document.querySelectorAll("[data-tab-footer]").forEach((el) => {
                el.hidden = el.dataset.tabFooter !== this.tab;
            });
        },
        setTab(name) {
            this.tab = name;
            if (this.persistKey) {
                localStorage.setItem(this.persistKey, name);
            }
            this.syncTabFooters();
            if (this.urlSync) {
                const url = new URL(window.location.href);
                url.searchParams.set("tab", name);
                url.hash = "";
                history.replaceState(null, "", url.toString());
            }
        },
        isTab(name) {
            return this.tab === name;
        },
        tabClass(name) {
            return this.tab === name ? "tab-active" : "";
        },
    }));

    // Select-/Wert-gesteuertes Ein-/Ausblenden von Abschnitten.
    Alpine.data("reveal", (initial) => ({
        value: initial,
        is(v) {
            return this.value === v;
        },
        isNot(v) {
            return this.value !== v;
        },
        isAny(...vals) {
            return vals.includes(this.value);
        },
        // value === v ? a : b — für CSP-konforme :bind-Ausdrücke.
        choose(v, a, b) {
            return this.value === v ? a : b;
        },
    }));

    // Mitarbeiter-Auswahlliste mit Sofort-Suche + Auswahlzähler.
    Alpine.data("userChecklist", (initialCount) => ({
        q: "",
        count: initialCount,
        // haystack (Label) ist serverseitig bereits kleingeschrieben.
        visible(haystack) {
            const t = this.q.toLowerCase().trim();
            return t === "" || haystack.includes(t);
        },
        adjust(e) {
            this.count += e.target.checked ? 1 : -1;
        },
    }));

    // Berechtigungs-Matrix: Live-Filter + Gruppen-Checkboxen (alle/keine).
    Alpine.data("permissionMatrix", () => ({
        filter: "",
        // haystack ist serverseitig bereits kleingeschrieben (Str::lower + @js).
        matches(haystack) {
            const t = this.filter.toLowerCase().trim();
            return t === "" || haystack.includes(t);
        },
        selectGroup(key) {
            this.setGroup(key, true);
        },
        clearGroup(key) {
            this.setGroup(key, false);
        },
        setGroup(key, checked) {
            this.$root.querySelectorAll('input[data-group="' + key + '"]').forEach((el) => {
                el.checked = checked;
            });
        },
    }));

    // Heute-Ansicht: Live-Anwesenheit, Soll/Ist, Saldo + Fortschritt.
    Alpine.data("todayCounters", (isLive, baseAttendance, entriesMin, target, renderedAtIso) => ({
        isLive,
        baseAttendance,
        entriesMin,
        target,
        renderedAt: 0,
        now: 0,
        init() {
            this.renderedAt = new Date(renderedAtIso).getTime();
            this.now = Date.now();
            if (this.isLive) {
                setInterval(() => {
                    this.now = Date.now();
                }, 1000);
            }
        },
        get extraMinutes() {
            return this.isLive ? Math.max(0, Math.floor((this.now - this.renderedAt) / 60000)) : 0;
        },
        get attendanceMin() {
            return this.baseAttendance + this.extraMinutes;
        },
        get untrackedMin() {
            return Math.max(0, this.attendanceMin - this.entriesMin);
        },
        get balance() {
            return this.attendanceMin - this.target;
        },
        get progress() {
            return this.target > 0 ? Math.min(100, Math.round((this.attendanceMin / this.target) * 100)) : 0;
        },
        fmt(m) {
            const sign = m < 0 ? "-" : "";
            const abs = Math.abs(m);
            return sign + Math.floor(abs / 60) + ":" + String(abs % 60).padStart(2, "0") + " h";
        },
        get attendanceFmt() {
            return this.fmt(this.attendanceMin);
        },
        get untrackedFmt() {
            return this.fmt(this.untrackedMin);
        },
        get balanceFmt() {
            return this.fmt(this.balance);
        },
        get untrackedBorderClass() {
            return this.untrackedMin > 0 ? "border-warning/40" : "border-base-300";
        },
        get untrackedTextClass() {
            return this.untrackedMin > 0 ? "text-warning" : "text-base-content";
        },
        get balanceTextClass() {
            return this.balance >= 0 ? "text-success" : "text-error";
        },
        get balanceProgressClass() {
            return this.balance >= 0 ? "progress-success" : "progress-warning";
        },
    }));

    // Diary-Formular: Eintragstyp steuert Pflichtfelder/Flags + Modus-Abschnitte.
    Alpine.data("diaryEntryForm", () => ({
        entryTypeId: "",
        flagsMap: {},
        flags: {},
        mode: "",
        init() {
            const d = this.$el.dataset;
            this.entryTypeId = d.entryType || "";
            this.flagsMap = JSON.parse(d.flagsMap || "{}");
            this.flags = JSON.parse(d.flags || "{}");
            this.mode = d.mode || "";
        },
        onTypeChange() {
            const id = String(this.entryTypeId || "0");
            this.flags = this.flagsMap[id] ?? {
                requires_customer: false,
                requires_address: false,
                requires_schedule: false,
                requires_tour: false,
                allow_priority: false,
                allow_tour: false,
                default_service_minutes: null,
                default_priority: null,
                default_status: 2,
            };
        },
        get hasEntryType() {
            return this.entryTypeId !== "0" && this.entryTypeId !== "";
        },
        isMode(m) {
            return this.mode === m;
        },
        get allowPriority() {
            return !!this.flags.allow_priority;
        },
        get requiresCustomer() {
            return !!this.flags.requires_customer;
        },
        get requiresSchedule() {
            return !!this.flags.requires_schedule;
        },
        get requiresAddress() {
            return !!this.flags.requires_address;
        },
        get allowTour() {
            return !!this.flags.allow_tour;
        },
    }));

    // Datei-Upload mit Größen-Check + Anzeige (auch Avatar mit remove-Flag).
    Alpine.data("fileUpload", (maxKb, tooLargeMsg) => ({
        fileName: null,
        fileSize: null,
        error: null,
        remove: false,
        maxKb,
        onChange(event) {
            this.error = null;
            const f = event.target.files && event.target.files[0];
            if (!f) {
                this.fileName = null;
                this.fileSize = null;
                return;
            }
            if (f.size > this.maxKb * 1024) {
                this.error = tooLargeMsg;
                event.target.value = "";
                this.fileName = null;
                this.fileSize = null;
                return;
            }
            this.fileName = f.name;
            this.fileSize = (f.size / 1024).toFixed(0) + " KB";
            this.remove = false;
        },
        get hasNoFile() {
            return !this.fileName;
        },
        get fileLabel() {
            return this.fileName + " (" + this.fileSize + ")";
        },
    }));

    // Anfahrt-Abrechnung (Org-Settings, Travel-Tab).
    Alpine.data("travelSettings", (enabled, mode, kmSource, roundTrip) => ({
        enabled,
        mode,
        kmSource,
        roundTrip,
        get enabledValue() {
            return this.enabled ? "1" : "0";
        },
        get roundTripValue() {
            return this.roundTrip ? "1" : "0";
        },
        isMode(m) {
            return this.mode === m;
        },
        isKmSource(v) {
            return this.kmSource === v;
        },
    }));

    // Krankmeldungs-Dialog: Tage-Berechnung + AU-Pflicht-Hinweis.
    Alpine.data("sickLeaveForm", (start, end, kind, threshold, hasExisting) => ({
        start,
        end,
        kind,
        threshold,
        hasExisting,
        get days() {
            if (!this.start || !this.end) {
                return 0;
            }
            const s = new Date(this.start);
            const e = new Date(this.end);
            if (isNaN(s) || isNaN(e) || e < s) {
                return 0;
            }
            return Math.round((e - s) / 86400000) + 1;
        },
        get requiresAu() {
            return !this.hasExisting && this.days >= this.threshold;
        },
        isKind(v) {
            return this.kind === v;
        },
    }));

    // Projekt-Formular: Eltern-Projekt erbt Kunde; steuert Fremdkunden-Auswahl.
    Alpine.data("projectForm", () => ({
        parentId: "",
        parentCustomers: {},
        customerId: "",
        foreignCustomersByCustomer: {},
        foreignCustomerId: "",
        init() {
            const d = this.$el.dataset;
            this.parentId = d.parentId || "";
            this.parentCustomers = JSON.parse(d.parentCustomers || "{}");
            this.customerId = d.customerId || "";
            this.foreignCustomersByCustomer = JSON.parse(d.foreignCustomers || "{}");
            this.foreignCustomerId = d.foreignCustomerId || "";
        },
        get hasParent() {
            return this.parentId !== "" && this.parentId !== null;
        },
        get noParent() {
            return !this.hasParent;
        },
        get parentCustomerId() {
            return this.hasParent ? this.parentCustomers[this.parentId] ?? "" : "";
        },
        get effectiveCustomerId() {
            return this.hasParent ? this.parentCustomerId : this.customerId;
        },
        get availableForeignCustomers() {
            return this.foreignCustomersByCustomer[this.effectiveCustomerId] ?? [];
        },
        get showForeignCustomer() {
            return !this.hasParent && this.availableForeignCustomers.length > 0;
        },
        isSelectedForeign(fc) {
            return fc.sqid === this.foreignCustomerId;
        },
        // Reaktiv (x-effect): Kunde vom Parent übernehmen, ungültigen Fremdkunden zurücksetzen.
        sync() {
            if (this.hasParent && this.parentCustomerId) {
                this.customerId = String(this.parentCustomerId);
            }
            if (!this.availableForeignCustomers.some((fc) => fc.sqid === this.foreignCustomerId)) {
                this.foreignCustomerId = "";
            }
        },
    }));

    // Gantt-Balken (Projekt-Planung): verschieben/resizen per Pointer, persistiert.
    Alpine.data("ganttBar", () => ({
        offset: 0,
        duration: 0,
        total: 1,
        fromIso: "",
        url: "",
        editable: false,
        color: "",
        csrf: "",
        _d: null,
        init() {
            const d = this.$el.dataset;
            this.offset = parseInt(d.offset, 10) || 0;
            this.duration = parseInt(d.duration, 10) || 0;
            this.total = parseInt(d.total, 10) || 1;
            this.fromIso = d.fromIso || "";
            this.url = d.url || "";
            this.editable = d.editable === "1";
            this.color = d.color || "";
            this.csrf = d.csrf || "";
        },
        get offsetPct() {
            return Math.max(0, (this.offset / this.total) * 100);
        },
        get widthPct() {
            return Math.max(2, (this.duration / this.total) * 100);
        },
        get cursorClass() {
            return this.editable ? "cursor-move" : "";
        },
        get barStyle() {
            return "left:" + this.offsetPct + "%; width:" + this.widthPct + "%; background-color:" + this.color;
        },
        get label() {
            const s = this.addDays(this.fromIso, this.offset);
            return this.fmt(s) + (this.duration > 0 ? "–" + this.fmt(this.addDays(this.fromIso, this.offset + this.duration)) : "");
        },
        _dayWidth(el) {
            const t = el.closest("[data-track]");
            return Math.max(1, t.clientWidth / this.total);
        },
        startMove(e) {
            if (this.editable) {
                this._begin(e, "move");
            }
        },
        startResize(e, edge) {
            if (this.editable) {
                this._begin(e, edge);
            }
        },
        _begin(e, mode) {
            e.preventDefault();
            const bar = e.target.closest("[data-bar]");
            this._d = { mode, x: e.clientX, o: this.offset, du: this.duration, dw: this._dayWidth(bar) };
            const move = (ev) => this._move(ev);
            const up = () => {
                this._end();
                window.removeEventListener("pointermove", move);
                window.removeEventListener("pointerup", up);
            };
            window.addEventListener("pointermove", move);
            window.addEventListener("pointerup", up);
        },
        _move(e) {
            if (!this._d) {
                return;
            }
            const dd = Math.round((e.clientX - this._d.x) / this._d.dw);
            if (this._d.mode === "move") {
                this.offset = Math.max(0, this._d.o + dd);
            } else if (this._d.mode === "l") {
                const newOffset = Math.max(0, Math.min(this._d.o + dd, this._d.o + this._d.du));
                this.duration = this._d.du + (this._d.o - newOffset);
                this.offset = newOffset;
            } else {
                this.duration = Math.max(0, this._d.du + dd);
            }
        },
        _end() {
            if (!this._d) {
                return;
            }
            const changed = this.offset !== this._d.o || this.duration !== this._d.du;
            this._d = null;
            if (changed) {
                this.persist();
            }
        },
        persist() {
            fetch(this.url, {
                method: "PATCH",
                headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": this.csrf },
                body: JSON.stringify({
                    start_date: this.addDays(this.fromIso, this.offset),
                    due_date: this.addDays(this.fromIso, this.offset + this.duration),
                }),
            }).catch(() => {});
        },
        addDays(iso, days) {
            const d = new Date(iso + "T00:00:00");
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0, 10);
        },
        fmt(iso) {
            const p = iso.split("-");
            return p[2] + "." + p[1] + ".";
        },
    }));

    // Arbeitszeit-Modell-Dialog. days nach Punkt-Keys d1..d7 (CSP: kein days[iso]).
    Alpine.data("wsForm", () => ({
        type: "flextime",
        unit: "minutes",
        d: { weekly: 0, daily: 0, breakAfter: 0, breakMin: 0 },
        days: {},
        init() {
            const c = JSON.parse(this.$el.dataset.config || "{}");
            this.type = c.type || "flextime";
            this.unit = c.unit || "minutes";
            this.d = { weekly: c.weekly ?? 0, daily: c.daily ?? 0, breakAfter: c.breakAfter ?? 0, breakMin: c.breakMin ?? 0 };
            const src = c.days || {};
            const days = {};
            for (let iso = 1; iso <= 7; iso++) {
                const v = src[iso] ?? src[String(iso)] ?? {};
                days["d" + iso] = { enabled: false, mode: "hours", hours: "", start: "", end: "", break: "", ...v };
            }
            this.days = days;
        },
        day(iso) {
            return this.days["d" + iso];
        },
        get unitLabel() {
            return this.unit === "hours" ? "Std." : "Min.";
        },
        get step() {
            return this.unit === "hours" ? "0.25" : "1";
        },
        isType(t) {
            return this.type === t;
        },
        unitClass(u) {
            return this.unit === u ? "btn-primary" : "btn-ghost";
        },
        isTypeAny(...ts) {
            return ts.includes(this.type);
        },
        dayModeIs(iso, mode) {
            return this.day(iso).mode === mode;
        },
        dayDisabled(iso) {
            return !this.day(iso).enabled;
        },
        dayEnabledValue(iso) {
            return this.day(iso).enabled ? "1" : "0";
        },
        dayRowClass(iso) {
            return this.day(iso).enabled ? "" : "opacity-50";
        },
        parse(v) {
            const n = parseFloat(String(v).replace(",", "."));
            return isNaN(n) ? 0 : n;
        },
        toMin(v) {
            const n = this.parse(v);
            return this.unit === "hours" ? Math.round(n * 60) : Math.round(n);
        },
        dayHours(iso) {
            const n = this.parse(this.day(iso)?.hours);
            return this.unit === "hours" ? n : +(n / 60).toFixed(4);
        },
        dayMinutes(iso) {
            const day = this.day(iso);
            if (!day || !day.enabled) {
                return 0;
            }
            if (day.mode === "times") {
                return Math.max(0, this.minutesBetween(day.start, day.end) - (parseInt(day.break, 10) || 0));
            }
            return this.toMin(day.hours);
        },
        dayMinutesFmt(iso) {
            return this.fmt(this.dayMinutes(iso));
        },
        dayMinutesLabel(iso) {
            return this.day(iso)?.enabled ? this.fmt(this.dayMinutes(iso)) : "–";
        },
        minutesBetween(start, end) {
            const re = /^\d{1,2}:\d{2}$/;
            if (!re.test(start || "") || !re.test(end || "")) {
                return 0;
            }
            const [sh, sm] = start.split(":").map((x) => parseInt(x, 10));
            const [eh, em] = end.split(":").map((x) => parseInt(x, 10));
            return Math.max(0, eh * 60 + em - (sh * 60 + sm));
        },
        fmt(min) {
            const m = Math.max(0, Math.round(min));
            return Math.floor(m / 60) + ":" + String(m % 60).padStart(2, "0") + " h";
        },
        get weeklyTotalMinutes() {
            return [1, 2, 3, 4, 5, 6, 7].reduce((sum, iso) => sum + this.dayMinutes(iso), 0);
        },
        get weeklyTotalFmt() {
            return this.fmt(this.weeklyTotalMinutes);
        },
        switchTo(u) {
            if (u === this.unit) {
                return;
            }
            const conv = (val) => {
                const mins = this.unit === "hours" ? this.parse(val) * 60 : this.parse(val);
                return u === "hours" ? +(mins / 60).toFixed(2) : Math.round(mins);
            };
            for (const k of Object.keys(this.d)) {
                this.d[k] = conv(this.d[k]);
            }
            for (const k of Object.keys(this.days)) {
                if (this.days[k] && this.days[k].hours !== undefined && this.days[k].hours !== "") {
                    this.days[k].hours = conv(this.days[k].hours);
                }
            }
            this.unit = u;
        },
    }));

    // Liegenschafts-Picker (Customer → Site → Building → Floor → Room).
    Alpine.data("facilityPicker", () => ({
        data: { customers: [], foreignCustomers: [], sites: [], buildings: [], floors: [], rooms: [] },
        withRoom: false,
        withForeignCustomer: false,
        customer_id: null,
        foreign_customer_id: null,
        site_id: null,
        building_id: null,
        floor_id: null,
        room_id: null,
        init() {
            const cfg = JSON.parse(this.$el.dataset.config || "{}");
            if (cfg.data) {
                this.data = cfg.data;
            }
            this.withRoom = !!cfg.withRoom;
            this.withForeignCustomer = !!cfg.withForeignCustomer;
            const i = cfg.initial ?? {};
            this.customer_id = i.customer_id ?? null;
            this.foreign_customer_id = i.foreign_customer_id ?? null;
            this.site_id = i.site_id ?? null;
            this.building_id = i.building_id ?? null;
            this.floor_id = i.floor_id ?? null;
            this.room_id = i.room_id ?? null;
            this.syncFromCurrent();
            this.autoSelectSingles();
            this.applySelection();
        },
        // Selects nach dem Rendern der x-for-Optionen auf den State setzen —
        // x-model greift sonst, bevor die Optionen existieren (leerer Dialog).
        applySelection() {
            this.$nextTick(() => {
                const pairs = [
                    ["customerSelect", this.customer_id],
                    ["foreignSelect", this.foreign_customer_id],
                    ["siteSelect", this.site_id],
                    ["buildingSelect", this.building_id],
                    ["floorSelect", this.floor_id],
                    ["roomSelect", this.room_id],
                ];
                for (const [ref, value] of pairs) {
                    const el = this.$refs[ref];
                    if (el) {
                        el.value = value == null ? "" : String(value);
                    }
                }
            });
        },
        autoSelectSingles() {
            if (this.customer_id != null && this.site_id == null && this.filteredSites.length === 1) {
                this.site_id = this.filteredSites[0].id;
            }
            if (this.site_id != null && this.building_id == null && this.filteredBuildings.length === 1) {
                this.building_id = this.filteredBuildings[0].id;
            }
            if (this.building_id != null && this.floor_id == null && this.filteredFloors.length === 1) {
                this.floor_id = this.filteredFloors[0].id;
            }
            if (this.withRoom && this.floor_id != null && this.room_id == null && this.filteredRooms.length === 1) {
                this.room_id = this.filteredRooms[0].id;
            }
        },
        get filteredSites() {
            if (this.customer_id == null) {
                return this.data.sites;
            }
            return this.data.sites.filter((s) => s.customer_id == null || s.customer_id === this.customer_id);
        },
        get filteredForeignCustomers() {
            if (this.customer_id == null) {
                return [];
            }
            return (this.data.foreignCustomers ?? []).filter((fc) => fc.customer_id === this.customer_id);
        },
        get filteredBuildings() {
            if (this.site_id == null) {
                if (this.customer_id == null) {
                    return this.data.buildings;
                }
                const siteIds = new Set(this.filteredSites.map((s) => s.id));
                return this.data.buildings.filter((b) => siteIds.has(b.site_id));
            }
            return this.data.buildings.filter((b) => b.site_id === this.site_id);
        },
        get filteredFloors() {
            if (this.building_id == null) {
                const bIds = new Set(this.filteredBuildings.map((b) => b.id));
                return this.data.floors.filter((f) => bIds.has(f.building_id));
            }
            return this.data.floors.filter((f) => f.building_id === this.building_id);
        },
        get filteredRooms() {
            let rooms = this.data.rooms;
            if (this.floor_id != null) {
                rooms = rooms.filter((r) => r.floor_id === this.floor_id);
            } else {
                const fIds = new Set(this.filteredFloors.map((f) => f.id));
                rooms = rooms.filter((r) => r.floor_id == null || fIds.has(r.floor_id));
            }
            if (this.customer_id != null) {
                rooms = rooms.filter((r) => r.customer_id == null || r.customer_id === this.customer_id);
            }
            return rooms;
        },
        get hasForeignCustomers() {
            return this.filteredForeignCustomers.length > 0;
        },
        // Anzeigename eines Geschosses — als Methode statt Template-Literal in
        // der Direktive (der @alpinejs/csp-Parser kennt keine Backticks).
        floorLabel(f) {
            return `${f.label} (${f.level})`;
        },
        syncFromCurrent() {
            if (this.room_id != null) {
                const room = this.data.rooms.find((r) => r.id === this.room_id);
                if (room) {
                    if (this.floor_id == null) {
                        this.floor_id = room.floor_id;
                    }
                    if (this.customer_id == null && room.customer_id != null) {
                        this.customer_id = room.customer_id;
                    }
                }
            }
            if (this.floor_id != null && this.building_id == null) {
                const floor = this.data.floors.find((f) => f.id === this.floor_id);
                if (floor) {
                    this.building_id = floor.building_id;
                }
            }
            if (this.building_id != null && this.site_id == null) {
                const building = this.data.buildings.find((b) => b.id === this.building_id);
                if (building) {
                    this.site_id = building.site_id;
                }
            }
            if (this.site_id != null && this.customer_id == null) {
                const site = this.data.sites.find((s) => s.id === this.site_id);
                if (site && site.customer_id != null) {
                    this.customer_id = site.customer_id;
                }
            }
        },
        onCustomerChange() {
            if (this.foreign_customer_id != null) {
                const fc = (this.data.foreignCustomers ?? []).find((f) => f.id === this.foreign_customer_id);
                if (!fc || fc.customer_id !== this.customer_id) {
                    this.foreign_customer_id = null;
                }
            }
            if (this.site_id != null) {
                const site = this.data.sites.find((s) => s.id === this.site_id);
                if (!site || (site.customer_id != null && site.customer_id !== this.customer_id)) {
                    this.site_id = null;
                    this.building_id = null;
                    this.floor_id = null;
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
            this.applySelection();
        },
        onSiteChange() {
            if (this.building_id != null) {
                const b = this.data.buildings.find((x) => x.id === this.building_id);
                if (!b || b.site_id !== this.site_id) {
                    this.building_id = null;
                    this.floor_id = null;
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
            this.applySelection();
        },
        onBuildingChange() {
            if (this.floor_id != null) {
                const f = this.data.floors.find((x) => x.id === this.floor_id);
                if (!f || f.building_id !== this.building_id) {
                    this.floor_id = null;
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
            this.applySelection();
        },
        onFloorChange() {
            if (this.room_id != null) {
                const r = this.data.rooms.find((x) => x.id === this.room_id);
                if (!r || r.floor_id !== this.floor_id) {
                    this.room_id = null;
                }
            }
            this.autoSelectSingles();
            this.applySelection();
        },
    }));

    // Tag-Auswahl: Schnellauswahl + Suche + Anlegen. Config via data-config (JSON).
    Alpine.data("tagPicker", () => {
        let byId = new Map();
        let newKey = 0;
        return {
            all: [],
            recentIds: [],
            quickLimit: 8,
            allowCreate: true,
            selected: [],
            query: "",
            open: false,
            highlight: 0,
            init() {
                const cfg = JSON.parse(this.$el.dataset.config || "{}");
                this.all = (cfg.all ?? []).map((t) => ({ id: String(t.id ?? ""), name: String(t.name ?? ""), color: t.color ?? null }));
                byId = new Map(this.all.map((t) => [t.id, t]));
                this.recentIds = (cfg.recentIds ?? []).map(String);
                this.quickLimit = cfg.quickLimit ?? 8;
                this.allowCreate = cfg.allowCreate !== false;
                const initialExisting = (cfg.selectedIds ?? []).map((id) => byId.get(String(id))).filter(Boolean).map((t) => ({ ...t, isNew: false, key: "e" + t.id }));
                const initialNew = (cfg.initialNew ?? []).map((n) => String(n).trim()).filter(Boolean).map((name) => ({ id: null, name, color: null, isNew: true, key: "n" + newKey++ }));
                this.selected = [...initialExisting, ...initialNew];
            },
            get existingIds() {
                return this.selected.filter((t) => !t.isNew).map((t) => t.id);
            },
            get newNames() {
                return this.selected.filter((t) => t.isNew).map((t) => t.name);
            },
            get newNamesText() {
                return this.newNames.join(", ");
            },
            get selectedKeyset() {
                return {
                    ids: new Set(this.selected.filter((t) => !t.isNew).map((t) => t.id)),
                    names: new Set(this.selected.map((t) => t.name.toLowerCase())),
                };
            },
            get quickPicks() {
                const ids = this.selectedKeyset.ids;
                const ordered = this.recentIds.length ? this.recentIds.map((id) => byId.get(id)).filter(Boolean) : this.all;
                return ordered.filter((t) => !ids.has(t.id)).slice(0, this.quickLimit);
            },
            get filtered() {
                const ids = this.selectedKeyset.ids;
                const q = this.query.trim().toLowerCase();
                const pool = this.all.filter((t) => !ids.has(t.id));
                const matches = q === "" ? pool : pool.filter((t) => t.name.toLowerCase().includes(q));
                return matches.slice(0, 8);
            },
            get canCreate() {
                if (!this.allowCreate) {
                    return false;
                }
                const q = this.query.trim();
                if (q === "") {
                    return false;
                }
                const ql = q.toLowerCase();
                if (this.selectedKeyset.names.has(ql)) {
                    return false;
                }
                return !this.all.some((t) => t.name.toLowerCase() === ql);
            },
            // CSP-Helfer (statt Inline-Ausdrücken im Template):
            get hasSelected() {
                return this.selected.length > 0;
            },
            get hasQuickPicks() {
                return this.quickPicks.length > 0;
            },
            get showMenu() {
                return this.open && (this.filtered.length > 0 || this.canCreate);
            },
            get queryTrimmed() {
                return this.query.trim();
            },
            chipClass(tag) {
                return tag.isNew ? "badge-success" : "badge-primary";
            },
            chipStyle(tag) {
                return tag.color ? "background-color:" + tag.color + ";border-color:" + tag.color + ";color:#fff" : "";
            },
            dotStyle(tag) {
                return "background:" + tag.color;
            },
            optionClass(idx) {
                // daisyUI 5: Markierung heißt menu-active („active" ist wirkungslos).
                return idx === this.highlight ? "menu-active" : "";
            },
            openMenu() {
                this.open = true;
            },
            close() {
                this.open = false;
            },
            onInput() {
                this.open = true;
                this.highlight = 0;
            },
            setHighlight(idx) {
                this.highlight = idx;
            },
            addExisting(tag) {
                if (!tag) {
                    return;
                }
                if (this.selected.some((t) => !t.isNew && t.id === tag.id)) {
                    return;
                }
                this.selected.push({ ...tag, isNew: false, key: "e" + tag.id });
                this.resetInput();
            },
            createNew() {
                const name = this.query.trim();
                if (name === "") {
                    return;
                }
                const ql = name.toLowerCase();
                const existing = this.all.find((t) => t.name.toLowerCase() === ql);
                if (existing) {
                    this.addExisting(existing);
                    return;
                }
                if (this.selected.some((t) => t.name.toLowerCase() === ql)) {
                    this.resetInput();
                    return;
                }
                this.selected.push({ id: null, name, color: null, isNew: true, key: "n" + newKey++ });
                this.resetInput();
            },
            remove(item) {
                this.selected = this.selected.filter((t) => t.key !== item.key);
            },
            enterPressed() {
                const list = this.filtered;
                if (list.length && this.highlight >= 0 && this.highlight < list.length) {
                    this.addExisting(list[this.highlight]);
                } else if (this.canCreate) {
                    this.createNew();
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
            resetInput() {
                this.query = "";
                this.highlight = 0;
                this.open = false;
            },
        };
    });

    // Signatur-Pad (Stundenzettel-Unterschrift + Unterschriften-Feld in
    // ausfüllbaren Formularen). Existiert $refs.sigInput, wird der Base64-PNG-
    // Wert bei jedem Strich mitgeschrieben (Capture-Modus ohne Submit-Hook).
    Alpine.data("signaturePad", () => ({
        pad: null,
        isEmpty: true,
        resizeHandler: null,
        customerName: "",
        customerRole: "",
        customerEmail: "",
        init() {
            const d = this.$el.dataset;
            this.customerName = d.name || "";
            this.customerRole = d.role || "";
            this.customerEmail = d.email || "";

            const SignaturePadClass = window.SignaturePad;
            if (!SignaturePadClass) {
                console.error("[signature-pad] window.SignaturePad fehlt");
                return;
            }
            const c = this.$refs.canvas;
            if (!c) {
                return;
            }
            this.pad = new SignaturePadClass(c, { penColor: "#111", backgroundColor: "rgba(255,255,255,0)" });
            this.pad.addEventListener("endStroke", () => {
                this.isEmpty = this.pad.isEmpty();
                if (this.$refs.sigInput) {
                    this.$refs.sigInput.value = this.pad.toDataURL("image/png");
                }
            });
            this.resizeHandler = () => this.resizeCanvas();
            window.addEventListener("resize", this.resizeHandler);
            requestAnimationFrame(() => this.resizeCanvas());
        },
        resizeCanvas() {
            const c = this.$refs.canvas;
            if (!c) {
                return;
            }
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const cssWidth = c.offsetWidth;
            const cssHeight = c.offsetHeight;
            if (cssWidth === 0 || cssHeight === 0) {
                requestAnimationFrame(() => this.resizeCanvas());
                return;
            }
            const data = this.pad ? this.pad.toData() : null;
            c.width = cssWidth * ratio;
            c.height = cssHeight * ratio;
            c.getContext("2d").scale(ratio, ratio);
            if (this.pad) {
                this.pad.clear();
                if (data && data.length) {
                    this.pad.fromData(data);
                }
                this.isEmpty = this.pad.isEmpty();
            }
        },
        clear() {
            this.pad?.clear();
            this.isEmpty = true;
            if (this.$refs.sigInput) {
                this.$refs.sigInput.value = "";
            }
        },
        prepare(e) {
            if (!this.pad || this.pad.isEmpty()) {
                e.preventDefault();
                return;
            }
            this.$refs.sigInput.value = this.pad.toDataURL("image/png");
        },
        destroy() {
            if (this.resizeHandler) {
                window.removeEventListener("resize", this.resizeHandler);
                this.resizeHandler = null;
            }
            this.pad?.off();
            this.pad = null;
        },
        get hasSignature() {
            return !this.isEmpty;
        },
        get submitDisabled() {
            return this.isEmpty || !this.customerName;
        },
    }));

    // Generischer Zeilen-Repeater (Objekt-Items mit benannten Feldern).
    // Config via data-*: data-items (JSON), data-prefix (Feldname-Präfix),
    // data-template (JSON-Vorlage für neue Zeilen). Feldnamen via fieldName(i, feld).
    Alpine.data("repeater", () => ({
        items: [],
        prefix: "items",
        template: {},
        init() {
            const d = this.$el.dataset;
            this.items = JSON.parse(d.items || "[]");
            this.prefix = d.prefix || "items";
            this.template = JSON.parse(d.template || "{}");
        },
        add() {
            this.items.push(JSON.parse(JSON.stringify(this.template)));
        },
        remove(i) {
            this.items.splice(i, 1);
        },
        fieldName(i, field) {
            return this.prefix + "[" + i + "][" + field + "]";
        },
        // Andere benannte Zeilen (für "sichtbar wenn"-Referenzen) — als Methode
        // statt Arrow-Filter in der Direktive (CSP-Build-Parser kennt keine =>).
        otherLabeledItems(it) {
            return this.items.filter((o) => o !== it && o.label);
        },
    }));

    // Bedingungslogik Formular-Ausfüllen (Feature 032, Rang 33): spiegelt
    // FormFieldDefinition::isVisible clientseitig. Config via data-Attribute
    // (JSON): data-conditions {key: {field,op,value}}, data-initial {key: string}
    // — Objekt-Argumente via @js() wären im CSP-Build nicht auswertbar
    // (JSON.parse-Wrapper). Der Wrapper trackt Quelle-Werte generisch über name.
    Alpine.data("formFill", () => ({
        vals: {},
        conditions: {},
        init() {
            this.conditions = JSON.parse(this.$el.dataset.conditions || "{}");
            this.vals = Object.assign({}, JSON.parse(this.$el.dataset.initial || "{}"));
        },
        track(e) {
            const t = e.target;
            if (!t || !t.name) return;
            const m = String(t.name).match(/^values\[([^\]]+)\]$/);
            if (!m) return;
            this.vals[m[1]] = t.type === "checkbox" ? (t.checked ? "1" : "0") : t.value;
        },
        visible(key) {
            const c = this.conditions[key];
            if (!c || !c.field) return true;
            const actual = (this.vals[c.field] ?? "").toString().trim();
            const val = (c.value ?? "").toString();
            switch (c.op || "eq") {
                case "filled":
                    return actual !== "" && actual !== "0";
                case "ne":
                    return actual !== val;
                case "in":
                    return val
                        .split(",")
                        .map((s) => s.trim())
                        .filter(Boolean)
                        .includes(actual);
                default:
                    return actual === val;
            }
        },
    }));

    // Event-Kategorie: Liste von Erinnerungs-Offsets (hinzufügen/entfernen).
    // items als {value}-Objekte → CSP-konformes x-model="it.value" (kein items[i]).
    Alpine.data("reminderOffsets", () => ({
        items: [],
        init() {
            this.items = JSON.parse(this.$el.dataset.items || "[]").map((v) => ({ value: v }));
        },
        add() {
            this.items.push({ value: 60 });
        },
        remove(i) {
            this.items.splice(i, 1);
        },
        fieldName(i) {
            return "reminder_offsets[" + i + "]";
        },
    }));

    // Plugin-Verbindungstest (Health-Check) im Admin-Dialog.
    Alpine.data("pluginHealthCheck", (url, csrf, failMsg) => ({
        testing: false,
        result: null,
        get idle() {
            return !this.testing;
        },
        get resultText() {
            const r = this.result;
            if (!r) {
                return "";
            }
            const msg = r.message ? " — " + r.message : "";
            const lat = r.latency_ms != null ? " (" + r.latency_ms + "ms)" : "";
            return r.status + msg + lat;
        },
        run() {
            this.testing = true;
            this.result = null;
            fetch(url, { method: "POST", headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" } })
                .then((r) => r.json())
                .then((d) => {
                    this.result = d;
                })
                .catch(() => {
                    this.result = { status: "failing", message: failMsg };
                })
                .finally(() => {
                    this.testing = false;
                });
        },
    }));

    // Theme-Editor: Live-Vorschau mit abgeleiteten Kontrastfarben
    // (ehemals Inline-x-data in admin/themes/_form_dialog — Objekte mit
    // Methoden kann der @alpinejs/csp-Parser nicht auswerten).
    // Config via data-config (JSON): { scheme, colors }.
    Alpine.data("themePreview", () => ({
        scheme: "light",
        colors: {},
        init() {
            const cfg = JSON.parse(this.$el.dataset.config || "{}");
            this.scheme = cfg.scheme ?? "light";
            this.colors = cfg.colors ?? {};
        },
        // Kontrastfarbe (dunkel/hell) zur übergebenen Hintergrundfarbe.
        content(hex) {
            try {
                const h = (hex || "").replace("#", "");
                if (h.length !== 6) return "#1f2937";
                const r = parseInt(h.substr(0, 2), 16);
                const g = parseInt(h.substr(2, 2), 16);
                const b = parseInt(h.substr(4, 2), 16);
                const l = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
                return l > 0.55 ? "#1f2937" : "#ffffff";
            } catch (e) {
                return "#1f2937";
            }
        },
        previewStyle() {
            let s = "color-scheme:" + this.scheme + ";";
            for (const k in this.colors) {
                s += "--color-" + k + ":" + this.colors[k] + ";";
            }
            s += "--color-base-content:" + this.content(this.colors["base-100"]) + ";";
            ["primary", "secondary", "accent", "neutral", "info", "success", "warning", "error"].forEach(
                (k) => {
                    s += "--color-" + k + "-content:" + this.content(this.colors[k]) + ";";
                },
            );
            return s;
        },
    }));

    // Rechnungs-Assistent: blendet Feldgruppen nach gewähltem Inhaltstyp um
    // (ehemals Inline-x-data + if-Statement in x-on:change — der CSP-Parser
    // kennt nur Ausdrücke, keine Statements). Initialwert via data-content.
    // MVP-462: lädt zusätzlich die Rechnungs-Vorschau (Partial vom Server)
    // debounced nach, sobald sich Kunde/Projekt/Endkunde/Zeitraum ändern.
    Alpine.data("invoiceContentSwitch", () => ({
        content: "service",
        previewTimer: null,
        init() {
            this.content = this.$el.dataset.content || "service";
            this.schedulePreview();
        },
        // Delegierter change-Handler des Wrappers: reagiert nur auf das
        // "content"-Select im Formular.
        onFormChange(event) {
            if (event.target && event.target.name === "content") {
                this.content = event.target.value;
            }
            // Änderungen INNERHALB der Vorschau (Ausschluss-Checkboxen) dürfen
            // keinen Reload auslösen — sonst verlöre die Auswahl ihren Stand.
            if (event.target && event.target.closest("[data-invoice-preview]")) {
                return;
            }
            this.schedulePreview();
        },
        schedulePreview() {
            const box = this.$root.querySelector("[data-invoice-preview]");
            if (!box) return;
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => {
                this.loadPreview();
            }, 350);
        },
        async loadPreview() {
            const box = this.$root.querySelector("[data-invoice-preview]");
            if (!box) return;
            const form = box.closest("form");
            if (!form || this.content !== "service") {
                clearHtml(box);
                return;
            }
            const fields = new FormData(form);
            const params = new URLSearchParams();
            ["customer_id", "project_id", "foreign_customer_id", "from", "to"].forEach((name) => {
                const value = fields.get(name);
                if (value) params.set(name, String(value));
            });
            if (!params.get("customer_id")) {
                clearHtml(box);
                return;
            }
            try {
                const res = await fetch(box.dataset.url || "", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": box.dataset.csrf || "",
                        "X-Requested-With": "XMLHttpRequest",
                        "Content-Type": "application/x-www-form-urlencoded",
                        Accept: "text/html",
                    },
                    body: params.toString(),
                });
                if (!res.ok) {
                    clearHtml(box);
                    return;
                }
                setHtml(box, trustedServerHtml(await res.text()));
            } catch {
                // Netzwerkfehler: Vorschau bleibt leer, das Formular funktioniert weiter.
                clearHtml(box);
            }
        },
    }));

    // Manuelles Projekt-Zusammenführen (projects/duplicates): Kunde wählen →
    // Ziel/Quelle aus dessen Projekten (ehemals Inline-x-data mit Getter).
    // Config via data-config (JSON): { customers, projects }.
    Alpine.data("projectManualMerge", () => ({
        customerKey: "",
        target: "",
        source: "",
        customers: [],
        projects: [],
        init() {
            const cfg = JSON.parse(this.$el.dataset.config || "{}");
            this.customers = cfg.customers ?? [];
            this.projects = cfg.projects ?? [];
        },
        get filtered() {
            return this.projects.filter((p) => p.ck === this.customerKey);
        },
        resetProjects() {
            this.target = "";
            this.source = "";
        },
    }));

    // Zahlungsabgleich (finance/reconciliation): Sammelbuchungs-Aufteilung —
    // je Detailzeile Auswahl + Ziel "typ:sqid"; Hidden-Felder leiten Typ/Id
    // daraus ab (ehemals Inline-x-data mit Split-Ausdrücken). Startzeilen
    // via data-rows (JSON): { index: { picked, target } }.
    Alpine.data("reconciliationSplit", () => ({
        rows: {},
        init() {
            this.rows = JSON.parse(this.$el.dataset.rows || "{}");
        },
        unpicked(i) {
            return !this.rows[i]?.picked;
        },
        // Abgewählt oder ohne Ziel → Allocation-Felder deaktivieren.
        idle(i) {
            return !this.rows[i]?.picked || !this.rows[i]?.target;
        },
        allocType(i) {
            return (this.rows[i]?.target || ":").split(":")[0];
        },
        allocId(i) {
            return (this.rows[i]?.target || ":").split(":")[1];
        },
    }));

    // Zahlungsabgleich: Vorschlagsliste — Checkbox je Vorschlag schaltet die
    // zugehörigen Allocation-Felder frei; erster Vorschlag vorausgewählt.
    Alpine.data("reconciliationPick", () => ({
        picked: { 0: true },
        unpicked(i) {
            return !this.picked[i];
        },
    }));

    // Dubletten-Listen (customers/projects duplicates): Sammel-Auswahl von
    // Paaren für die Bulk-Zusammenführung (ehemals Inline-x-data). Alle
    // Paar-Schlüssel via data-pairs (JSON) — Basis für „Alle auswählen".
    Alpine.data("pairSelection", () => ({
        selected: [],
        pairs: [],
        init() {
            this.pairs = JSON.parse(this.$el.dataset.pairs || "[]");
        },
        hasSelection() {
            return this.selected.length > 0;
        },
        allSelected() {
            return this.pairs.length > 0 && this.selected.length === this.pairs.length;
        },
        toggleAll() {
            this.selected = this.allSelected() ? [] : [...this.pairs];
        },
        clear() {
            this.selected = [];
        },
    }));

    // Fernwartungs-Inbox: abhängige Kunde→Fremdkunde→Projekt-Auswahl. Maps als
    // data-Attribute am x-data-Element:
    //   data-foreign-map → { kundeSqid: [{id, name}] }
    //   data-project-map → { kundeSqid: [{id, name, fc}] } (fc = Fremdkunden-Sqid|null)
    Alpine.data("remoteAssign", () => ({
        customer: "",
        foreign: "",
        allChecked: false,
        foreignMap: {},
        projectMap: {},
        init() {
            // Maps liegen EINMAL pro Seite in #remote-assign-maps (statt an
            // jedem Formular dupliziert); eigene data-Attribute gewinnen.
            const shared = document.getElementById("remote-assign-maps");
            const src = { ...(shared?.dataset ?? {}), ...this.$el.dataset };
            this.foreignMap = JSON.parse(src.foreignMap || "{}");
            this.projectMap = JSON.parse(src.projectMap || "{}");
        },
        get foreignCustomers() {
            return this.foreignMap[this.customer] ?? [];
        },
        get hasForeignCustomers() {
            return this.foreignCustomers.length > 0;
        },
        // Ohne Fremdkunden-Wahl nur firmendirekte Projekte (fc = null).
        get projects() {
            const fc = this.foreign === "" ? null : this.foreign;
            return (this.projectMap[this.customer] ?? []).filter((p) => (p.fc ?? null) === fc);
        },
        get noCustomer() {
            return this.customer === "";
        },
        resetForeign() {
            this.foreign = "";
        },
        toggleAll() {
            this.$refs.list
                ?.querySelectorAll('input[type=checkbox][name="pending_ids[]"]')
                .forEach((cb) => (cb.checked = this.allChecked));
        },
        // Vorbefüllung Kunde → Fremdkunde: der Fremdkunden-Select wird erst
        // nach der Kundenwahl gerendert (x-for), daher Endkunde im nextTick.
        applyPreset(customerSqid, foreignSqid) {
            this.customer = customerSqid;
            this.foreign = "";
            if (foreignSqid) {
                this.$nextTick(() => {
                    this.foreign = foreignSqid;
                });
            }
        },
        // Vorschlags-Badge einer Sitzungszeile: wählt Kunde (+ Endkunde) und
        // markiert alle Zeilen mit demselben Vorschlag (data-suggest-*).
        applySuggestion(evt) {
            const ds = evt.currentTarget?.dataset ?? {};
            const sqid = ds.suggestCustomer ?? "";
            if (!sqid) return;
            const fc = ds.suggestForeign ?? "";
            this.applyPreset(sqid, fc);
            this.$refs.list?.querySelectorAll("tr").forEach((tr) => {
                const cb = tr.querySelector('input[type=checkbox][name="pending_ids[]"]');
                if (cb) cb.checked = tr.dataset.suggestCustomer === sqid && (tr.dataset.suggestForeign ?? "") === fc;
            });
        },
    }));

    // Fernwartungs-Inbox: Zuweisungsvorschlag einer unbekannten Geräte-ID.
    // data-suggest = {shared, customer(Sqid), asset(Sqid), matchcode}; apply()
    // befüllt nur die Formulare vor — gebucht wird weiterhin per Submit.
    Alpine.data("remoteSuggest", () => ({
        suggest: {},
        init() {
            this.suggest = JSON.parse(this.$el.dataset.suggest || "{}");
        },
        apply() {
            // $root statt $el: in Direktiven zeigt $el auf den Klick-Button.
            const root = this.$root;
            const s = this.suggest;
            const tabs = root.querySelectorAll('input[type=radio].tab');
            if (s.shared) {
                root.querySelectorAll('input[type=checkbox][name="shared_remote"]').forEach((cb) => (cb.checked = true));
            }
            if (s.asset) {
                const sel = root.querySelector('select[name="asset_id"]');
                if (sel) {
                    sel.value = s.asset;
                    sel.dispatchEvent(new Event("change", { bubbles: true }));
                }
                tabs[0]?.click();
            } else if (s.customer) {
                // Kunde + Endkunde über die remoteAssign-Komponente des
                // „Neues Gerät"-Formulars setzen (kaskadierende Selects).
                const form = root.querySelector('form[x-data="remoteAssign"]');
                const data = form && window.Alpine ? window.Alpine.$data(form) : null;
                if (data && typeof data.applyPreset === "function") {
                    data.applyPreset(s.customer, s.foreign || "");
                }
                tabs[1]?.click();
            }
            if (s.matchcode) {
                root.querySelectorAll('input[name="matchcode"]').forEach((inp) => (inp.value = s.matchcode));
                root.querySelectorAll('input[name="matchcode_scope"]').forEach((inp) => (inp.value = s.matchcodeScope || "customer"));
            }
        },
    }));
}

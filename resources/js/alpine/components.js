/*
 * Zentrale Alpine-Komponenten (Alpine.data). Voraussetzung für den CSP-Build
 * (@alpinejs/csp): x-data referenziert NUR registrierte Namen, und Direktiven
 * dürfen ausschließlich Property-/Getter-Zugriffe oder Methodenaufrufe enthalten
 * (keine Inline-Ausdrücke, keine Operatoren/Ternaries). Funktioniert auch im
 * Standard-Build, daher migrierbar OHNE Build-Wechsel.
 */
import { clearHtml, setHtml, trustedServerHtml } from "../lib/html.js";
import { getJson, patchJson, postJson, request } from "../lib/http.js";
import { __ } from "../i18n.js";

/** @typedef {HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement} FormControl */

/** @param {import("alpinejs").Alpine} Alpine */
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
    Alpine.data("stopwatch", (/** @type {string} */ startedIso) => ({
        s: 0,
        init() {
            const started = new Date(startedIso).getTime();
            this.s = Math.max(0, Math.floor((Date.now() - started) / 1000));
            setInterval(() => {
                this.s++;
            }, 1000);
        },
        get display() {
            const p = (/** @type {number} */ n) => String(n).padStart(2, "0");
            return (
                p(Math.floor(this.s / 3600)) +
                ":" +
                p(Math.floor((this.s % 3600) / 60)) +
                ":" +
                p(this.s % 60)
            );
        },
        get displayShort() {
            const p = (/** @type {number} */ n) => String(n).padStart(2, "0");
            return (
                p(Math.floor(this.s / 3600)) +
                ":" +
                p(Math.floor((this.s % 3600) / 60))
            );
        },
    }));

    // Generische Tab-Umschaltung (CSP-konform via Methoden/Getter statt
    // Inline-Ausdrücken). Optionen per data-Attribut am x-data-Element:
    //   data-tab-persist="<key>"  → localStorage-Persistenz (z. B. Dashboard)
    //   data-tab-url-sync         → ?tab=-Query/Hash-Sync via history.replaceState
    //   data-tab-allowed="a,b,c"  → erlaubte Werte; Fallback = erster Eintrag
    Alpine.data("tabs", (/** @type {string} */ initial) => ({
        tab: initial,
        /** @type {string | null} */
        persistKey: null,
        urlSync: false,
        /** @type {string[] | null} */
        allowed: null,
        init() {
            const d = this.$el.dataset;
            this.persistKey = d.tabPersist || null;
            this.urlSync = d.tabUrlSync !== undefined;
            this.allowed = d.tabAllowed ? d.tabAllowed.split(",") : null;
            // Boolean(v) schließt null aus, daher der Cast im includes().
            const ok = /** @param {string | null} v @returns {v is string} */ (v) =>
                Boolean(v) && (!this.allowed || this.allowed.includes(/** @type {string} */ (v)));
            if (this.persistKey) {
                const stored = localStorage.getItem(this.persistKey);
                if (ok(stored)) {
                    this.tab = stored;
                }
            }
            if (this.urlSync) {
                const fromQuery = new URLSearchParams(
                    window.location.search,
                ).get("tab");
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
            /** @type {NodeListOf<HTMLElement>} */ (
                document.querySelectorAll("[data-tab-footer]")
            ).forEach((el) => {
                el.hidden = el.dataset.tabFooter !== this.tab;
            });
        },
        /** @param {string} name */
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
        /** @param {string} name */
        isTab(name) {
            return this.tab === name;
        },
        /** @param {string} name */
        tabClass(name) {
            return this.tab === name ? "tab-active" : "";
        },
    }));

    // Select-/Wert-gesteuertes Ein-/Ausblenden von Abschnitten.
    /** @typedef {string | number | boolean | null} RevealValue Startwert aus @js(), x-model kann ihn ersetzen. */
    Alpine.data("reveal", (/** @type {RevealValue} */ initial) => ({
        value: initial,
        /** @param {RevealValue} v */
        is(v) {
            return this.value === v;
        },
        /** @param {RevealValue} v */
        isNot(v) {
            return this.value !== v;
        },
        /** @param {...RevealValue} vals */
        isAny(...vals) {
            return vals.includes(this.value);
        },
        // Gegenstück zu isAny — der CSP-Evaluator kennt kein „!“.
        /** @param {...RevealValue} vals */
        isNone(...vals) {
            return !vals.includes(this.value);
        },
        // value === v ? a : b — für CSP-konforme :bind-Ausdrücke.
        /** @param {RevealValue} v @param {string} a @param {string} b */
        choose(v, a, b) {
            return this.value === v ? a : b;
        },
    }));

    // Personenauswahl der DSGVO-Auskunft (MVP-878): Suche je Betroffenenart
    // gegen den org-gescopten JSON-Endpunkt statt aller Datensätze im Markup.
    Alpine.data("subjectSearch", () => ({
        kind: "",
        q: "",
        /** @type {Array<{sqid: string, label: string}>} */
        results: [],
        /** @type {number | null} */
        timer: null,
        url: "",
        init() {
            // $el ist nur in init() die Wurzel; in Handlern das auslösende Feld.
            this.kind = this.$el.dataset.kind || "";
            this.url = this.$el.dataset.url || "";
            this.search();
        },
        onInput() {
            clearTimeout(this.timer ?? undefined);
            this.timer = setTimeout(() => this.search(), 250);
        },
        async search() {
            const params = new URLSearchParams({ kind: this.kind, q: this.q });
            const res = /** @type {import("../lib/http.js").JsonResult<{ items?: Array<{sqid: string, label: string}> }>} */ (
                await getJson(`${this.url}?${params.toString()}`)
            );
            this.results = res.ok && Array.isArray(res.data?.items) ? res.data.items : [];
        },
        isEmpty() {
            return this.results.length === 0;
        },
    }));

    // Abhängiges Select: Kindoptionen, deren `parent` zur gewählten
    // Elternauswahl passt (Vollscan 2026-08-23, I1 — die Inline-Variante
    // nutzte eine Arrow-Funktion, die der CSP-Evaluator nicht kennt).
    // items: [{id, name, parent}], parent: initial gewählte Eltern-ID.
    // Optionen per data-items: ein Array-Argument im x-data macht Js::from zu
    // JSON.parse(…), das der CSP-Evaluator nicht kennt (Komponente tot).
    Alpine.data("dependentSelect", (/** @type {string} */ parent) => ({
        parent: parent,
        /** @type {Array<{id: string, name: string, parent: string}>} */
        items: [],
        init() {
            this.items = JSON.parse(this.$el.dataset.items || "[]");
        },
        filtered() {
            return this.items.filter((item) => item.parent === this.parent);
        },
        /** @param {string} id @param {string} current */
        isSelected(id, current) {
            return id === current;
        },
    }));

    // Kunde → Fremdkunde (Domainverwaltung): Optionen je Kunde aus einer
    // Map; Wechsel des Kunden setzt die Fremdkunden-Auswahl zurück.
    /** @typedef {Record<string, Array<{sqid: string, name: string}>>} ForeignByCustomer Kunden-Sqid → Fremdkunden */
    Alpine.data("foreignCustomerPicker", (
        /** @type {ForeignByCustomer | null} */ map,
        /** @type {string} */ customer,
        /** @type {string} */ foreign,
    ) => ({
        /** @type {ForeignByCustomer} */
        map: map && typeof map === "object" ? map : {},
        customer: customer,
        foreign: foreign,
        init() {
            this.$watch("customer", () => {
                this.foreign = "";
            });
        },
        options() {
            return this.map[this.customer] || [];
        },
        hasOptions() {
            return this.options().length > 0;
        },
    }));

    // Halterwahl im Reselling-Register (Feature 152): Kunde wählen, der
    // Fremdkunden-Schritt erscheint nur, wenn dieser Kunde Fremdkunden hat;
    // Kundenwechsel setzt die Fremdkunden-Auswahl zurück.
    // Startwerte kommen als data-Attribute (CSP-Build: keine Ausdrücke im
    // x-data), die Karte Kunde → Fremdkunden als data-map (JSON).
    Alpine.data("resaleHolderPicker", () => ({
        /** @type {ForeignByCustomer} */
        map: {},
        holder: "none",
        customer: "",
        foreign: "",
        init() {
            const data = this.$root.dataset;
            this.map = JSON.parse(data.map || "{}");
            this.holder = data.holder || "none";
            this.customer = data.customer || "";
            this.foreign = data.foreign || "";
            this.$watch("customer", (value, previous) => {
                if (previous !== undefined && value !== previous) {
                    this.foreign = "";
                }
            });
        },
        needsCustomer() {
            return this.holder === "customer" || this.holder === "partner" || this.holder === "foreign";
        },
        options() {
            return this.map[this.customer] || [];
        },
        hasOptions() {
            return this.options().length > 0;
        },
        showForeign() {
            return this.needsCustomer() && this.holder !== "partner" && this.customer !== "" && this.hasOptions();
        },
        noForeign() {
            return this.holder === "foreign" && this.customer !== "" && !this.hasOptions();
        },
    }));

    // Aufklappbarer Baum in einer Tabelle (Kostengruppen-Pivot, MVP-648).
    // Der Zustand hängt an den Nummern, nicht an Zeilenindizes - sonst ginge
    // er beim Ebenenwechsel verloren.
    Alpine.data("treeTable", (/** @type {number[] | null} */ openInitially) => ({
        /** @type {Record<number, boolean>} */
        open: {},
        init() {
            // Die erste Ebene steht offen: Wer die Seite aufschlägt, will die
            // Gliederung sehen, nicht erst klicken.
            (openInitially || []).forEach((code) => {
                this.open[code] = true;
            });
        },
        /** @param {number} code */
        toggle(code) {
            this.open[code] = !this.open[code];
        },
        /** @param {number} code */
        isOpen(code) {
            return this.open[code] === true;
        },
        // Sichtbar ist eine Zeile nur, wenn jeder Vorfahr offen steht.
        /** @param {...number} parents */
        visible(...parents) {
            return parents.every((code) => this.open[code] === true);
        },
        /** @param {number} code */
        caret(code) {
            return this.open[code] === true ? "expand_more" : "chevron_right";
        },
    }));

    // Mitarbeiter-Auswahlliste mit Sofort-Suche + Auswahlzähler.
    Alpine.data("userChecklist", (/** @type {number} */ initialCount) => ({
        q: "",
        count: initialCount,
        // haystack (Label) ist serverseitig bereits kleingeschrieben.
        /** @param {string} haystack */
        visible(haystack) {
            const t = this.q.toLowerCase().trim();
            return t === "" || haystack.includes(t);
        },
        /** @param {Event & {target: HTMLInputElement}} e */
        adjust(e) {
            this.count += e.target.checked ? 1 : -1;
        },
    }));

    // Berechtigungs-Matrix: Live-Filter + Gruppen-Checkboxen (alle/keine).
    Alpine.data("permissionMatrix", () => ({
        filter: "",
        // haystack ist serverseitig bereits kleingeschrieben (Str::lower + @js).
        /** @param {string} haystack */
        matches(haystack) {
            const t = this.filter.toLowerCase().trim();
            return t === "" || haystack.includes(t);
        },
        /** @param {string} key */
        selectGroup(key) {
            this.setGroup(key, true);
        },
        /** @param {string} key */
        clearGroup(key) {
            this.setGroup(key, false);
        },
        /** @param {string} key @param {boolean} checked */
        setGroup(key, checked) {
            /** @type {NodeListOf<HTMLInputElement>} */ (
                this.$root.querySelectorAll('input[data-group="' + key + '"]')
            ).forEach((el) => {
                el.checked = checked;
            });
        },
    }));

    // Heute-Ansicht: Live-Anwesenheit, Soll/Ist, Saldo + Fortschritt.
    Alpine.data(
        "todayCounters",
        (
            /** @type {boolean} */ isLive,
            /** @type {number} */ baseAttendance,
            /** @type {number} */ entriesMin,
            /** @type {number} */ target,
            /** @type {string} */ renderedAtIso,
        ) => ({
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
                return this.isLive
                    ? Math.max(
                          0,
                          Math.floor((this.now - this.renderedAt) / 60000),
                      )
                    : 0;
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
                return this.target > 0
                    ? Math.min(
                          100,
                          Math.round((this.attendanceMin / this.target) * 100),
                      )
                    : 0;
            },
            /** @param {number} m */
            fmt(m) {
                const sign = m < 0 ? "-" : "";
                const abs = Math.abs(m);
                return (
                    sign +
                    Math.floor(abs / 60) +
                    ":" +
                    String(abs % 60).padStart(2, "0") +
                    " h"
                );
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
                return this.untrackedMin > 0
                    ? "border-warning/40"
                    : "border-base-300";
            },
            get untrackedTextClass() {
                return this.untrackedMin > 0
                    ? "text-warning"
                    : "text-base-content";
            },
            get balanceTextClass() {
                return this.balance >= 0 ? "text-success" : "text-error";
            },
            get balanceProgressClass() {
                return this.balance >= 0
                    ? "progress-success"
                    : "progress-warning";
            },
        }),
    );

    // Diary-Formular: Eintragstyp steuert Pflichtfelder/Flags + Modus-Abschnitte.
    /**
     * EntryType::flagsArray() — data-flags kann leer ({}) sein.
     * @typedef {Object} EntryTypeFlags
     * @property {boolean} [requires_customer]
     * @property {boolean} [requires_address]
     * @property {boolean} [requires_schedule]
     * @property {boolean} [requires_tour]
     * @property {boolean} [allow_priority]
     * @property {boolean} [allow_tour]
     * @property {number | null} [default_service_minutes]
     * @property {number | string | null} [default_priority]
     * @property {number | null} [default_status]
     */
    Alpine.data("diaryEntryForm", () => ({
        entryTypeId: "",
        /** @type {Record<string, EntryTypeFlags>} */
        flagsMap: {},
        /** @type {EntryTypeFlags} */
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
        /** @param {string} m */
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

    // Artikel-Picker in Belegpositionen (Feature 140): Auswahl belegt
    // Beschreibung/Einheit/Einzelpreis des umgebenden Formulars vor — die
    // Werte bleiben Positionswerte und frei editierbar. Läuft direkt auf dem
    // <select>; die Karte kommt als data-articles (Sqid → Vorbelegung).
    // Artikel-/Variantenauswahl einer Belegposition (Feature 140/160): belegt
    // Beschreibung, Einheit und Einzelpreis vor; Varianten werden je Artikel
    // eingesetzt; ein Preis in fremder Währung wird nie still übernommen.
    /**
     * Variante aus components/article-picker (Sqid, Preis als Dezimal-String).
     * @typedef {Object} ArticleVariantEntry
     * @property {string} id
     * @property {string} label
     * @property {string | null} name
     * @property {string | null} unit_price
     * @property {string | null} currency
     */
    /**
     * @typedef {Object} ArticleEntry
     * @property {string} description
     * @property {string | null} unit
     * @property {string | null} unit_price
     * @property {string | null} currency
     * @property {ArticleVariantEntry[]} variants
     */
    Alpine.data("articleItemPicker", () => ({
        /** @type {Record<string, ArticleEntry>} */
        map: {},
        currency: "",
        init() {
            this.map = JSON.parse(this.$root.dataset.articles || "{}");
            this.currency = this.$root.dataset.currency || "";
        },
        /** @param {string} name @returns {FormControl | null} */
        field(name) {
            const form = this.$root.closest("form");
            return form ? /** @type {FormControl | null} */ (form.elements.namedItem(name)) : null;
        },
        /** @param {Event & {target: FormControl | null}} event */
        onChange(event) {
            if (!event.target) return;
            if (event.target.name === "article_id") this.applyArticle();
            if (event.target.name === "article_variant_id") this.applyVariant();
        },
        /** @param {string} name @param {string | null | undefined} value */
        fill(name, value) {
            const field = this.field(name);
            if (!field || value === null || value === undefined || value === "") return;
            field.value = value;
            field.dispatchEvent(new Event("input", { bubbles: true }));
        },
        /** @param {string | null} entryCurrency */
        priceAllowed(entryCurrency) {
            return !this.currency || !entryCurrency || entryCurrency === this.currency;
        },
        /** @param {boolean} show */
        noteCurrency(show) {
            const note = this.$root.querySelector("[data-currency-note]");
            if (note) note.classList.toggle("hidden", !show);
        },
        applyArticle() {
            const select = this.field("article_id");
            const entry = this.map[String(select ? select.value : "")];
            this.renderVariants(entry);
            if (!entry) {
                this.noteCurrency(false);
                return;
            }
            this.fill("description", entry.description);
            this.fill("unit", entry.unit);
            if (this.priceAllowed(entry.currency)) {
                this.fill("unit_price", entry.unit_price);
                this.noteCurrency(false);
            } else {
                const price = this.field("unit_price");
                if (price) price.value = "";
                this.noteCurrency(true);
            }
        },
        /** @param {ArticleEntry | undefined} entry */
        renderVariants(entry) {
            const select = /** @type {HTMLSelectElement | null} */ (this.field("article_variant_id"));
            if (!select) return;
            while (select.options.length > 1) select.remove(1);
            (entry && entry.variants ? entry.variants : []).forEach((v) => {
                const option = document.createElement("option");
                option.value = v.id;
                option.textContent = v.label;
                select.appendChild(option);
            });
            select.value = "";
        },
        applyVariant() {
            const article = this.field("article_id");
            const variantSelect = this.field("article_variant_id");
            const entry = this.map[String(article ? article.value : "")];
            if (!entry || !variantSelect) return;
            const variant = (entry.variants || []).find((v) => v.id === variantSelect.value);
            if (!variant) return;
            const description = this.field("description");
            if (description && variant.name && (description.value === "" || description.value === entry.description)) {
                this.fill("description", entry.description + " – " + variant.name);
            }
            if (this.priceAllowed(variant.currency)) {
                this.fill("unit_price", variant.unit_price);
                this.noteCurrency(false);
            } else {
                const price = this.field("unit_price");
                if (price) price.value = "";
                this.noteCurrency(true);
            }
        },
    }));

    // Datei-Upload mit Größen-Check + Anzeige (auch Avatar mit remove-Flag).
    Alpine.data("fileUpload", (/** @type {number} */ maxKb, /** @type {string} */ tooLargeMsg) => ({
        /** @type {string | null} */
        fileName: null,
        /** @type {string | null} */
        fileSize: null,
        /** @type {string | null} */
        error: null,
        remove: false,
        maxKb,
        /** @param {Event & {target: HTMLInputElement}} event */
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
    Alpine.data("travelSettings", (
        /** @type {boolean} */ enabled,
        /** @type {string} */ mode,
        /** @type {string} */ kmSource,
        /** @type {boolean} */ roundTrip,
    ) => ({
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
        /** @param {string} m */
        isMode(m) {
            return this.mode === m;
        },
        /** @param {string} v */
        isKmSource(v) {
            return this.kmSource === v;
        },
    }));

    // Katalogquelle (MVP-1072): Open Masterdata ist ein Webservice — Datei-
    // und Abrufeinstellungen weichen den Zugangsdaten des Großhändlers.
    Alpine.data("catalogSourceForm", (/** @type {string} */ format) => ({
        format,
        isOmd() {
            return this.format === "omd";
        },
    }));

    // Krankmeldungs-Dialog: Tage-Berechnung + AU-Pflicht-Hinweis.
    Alpine.data(
        "sickLeaveForm",
        (
            /** @type {string | null} */ start,
            /** @type {string | null} */ end,
            /** @type {string} */ kind,
            /** @type {number} */ threshold,
            /** @type {boolean} */ hasExisting,
        ) => ({
            start,
            end,
            kind,
            threshold,
            hasExisting,
            get days() {
                if (!this.start || !this.end) {
                    return 0;
                }
                const s = new Date(this.start).getTime();
                const e = new Date(this.end).getTime();
                if (isNaN(s) || isNaN(e) || e < s) {
                    return 0;
                }
                return Math.round((e - s) / 86400000) + 1;
            },
            get requiresAu() {
                return !this.hasExisting && this.days >= this.threshold;
            },
            /** @param {string} v */
            isKind(v) {
                return this.kind === v;
            },
        }),
    );

    // Projekt-Formular: Eltern-Projekt erbt Kunde; steuert Fremdkunden-Auswahl.
    Alpine.data("projectForm", () => ({
        parentId: "",
        /** @type {Record<string, string>} Eltern-Projekt → Kunde */
        parentCustomers: {},
        customerId: "",
        /** @type {ForeignByCustomer} */
        foreignCustomersByCustomer: {},
        foreignCustomerId: "",
        init() {
            const d = this.$el.dataset;
            this.parentId = d.parentId || "";
            this.parentCustomers = JSON.parse(d.parentCustomers || "{}");
            this.customerId = d.customerId || "";
            this.foreignCustomersByCustomer = JSON.parse(
                d.foreignCustomers || "{}",
            );
            this.foreignCustomerId = d.foreignCustomerId || "";
        },
        get hasParent() {
            return this.parentId !== "" && this.parentId !== null;
        },
        get noParent() {
            return !this.hasParent;
        },
        get parentCustomerId() {
            return this.hasParent
                ? (this.parentCustomers[this.parentId] ?? "")
                : "";
        },
        get effectiveCustomerId() {
            return this.hasParent ? this.parentCustomerId : this.customerId;
        },
        get availableForeignCustomers() {
            return (
                this.foreignCustomersByCustomer[this.effectiveCustomerId] ?? []
            );
        },
        get showForeignCustomer() {
            return !this.hasParent && this.availableForeignCustomers.length > 0;
        },
        /** @param {{sqid: string}} fc */
        isSelectedForeign(fc) {
            return fc.sqid === this.foreignCustomerId;
        },
        // Reaktiv (x-effect): Kunde vom Parent übernehmen, ungültigen Fremdkunden zurücksetzen.
        sync() {
            if (this.hasParent && this.parentCustomerId) {
                this.customerId = String(this.parentCustomerId);
            }
            if (
                !this.availableForeignCustomers.some(
                    (fc) => fc.sqid === this.foreignCustomerId,
                )
            ) {
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
        /** @type {{mode: string, x: number, o: number, du: number, dw: number} | null} */
        _d: null,
        init() {
            const d = this.$el.dataset;
            this.offset = parseInt(d.offset ?? "", 10) || 0;
            this.duration = parseInt(d.duration ?? "", 10) || 0;
            this.total = parseInt(d.total ?? "", 10) || 1;
            this.fromIso = d.fromIso || "";
            this.url = d.url || "";
            this.editable = d.editable === "1";
            this.color = d.color || "";
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
            return (
                "left:" +
                this.offsetPct +
                "%; width:" +
                this.widthPct +
                "%; background-color:" +
                this.color
            );
        },
        get label() {
            const s = this.addDays(this.fromIso, this.offset);
            return (
                this.fmt(s) +
                (this.duration > 0
                    ? "–" +
                      this.fmt(
                          this.addDays(
                              this.fromIso,
                              this.offset + this.duration,
                          ),
                      )
                    : "")
            );
        },
        /** @param {Element} el */
        _dayWidth(el) {
            const t = /** @type {Element} */ (el.closest("[data-track]"));
            return Math.max(1, t.clientWidth / this.total);
        },
        /** @param {PointerEvent & {target: Element}} e */
        startMove(e) {
            if (this.editable) {
                this._begin(e, "move");
            }
        },
        /** @param {PointerEvent & {target: Element}} e @param {"l" | "r"} edge */
        startResize(e, edge) {
            if (this.editable) {
                this._begin(e, edge);
            }
        },
        /** @param {PointerEvent & {target: Element}} e @param {string} mode */
        _begin(e, mode) {
            e.preventDefault();
            const bar = /** @type {Element} */ (e.target.closest("[data-bar]"));
            this._d = {
                mode,
                x: e.clientX,
                o: this.offset,
                du: this.duration,
                dw: this._dayWidth(bar),
            };
            const move = (/** @type {PointerEvent} */ ev) => this._move(ev);
            const up = () => {
                this._end();
                window.removeEventListener("pointermove", move);
                window.removeEventListener("pointerup", up);
            };
            window.addEventListener("pointermove", move);
            window.addEventListener("pointerup", up);
        },
        /** @param {PointerEvent} e */
        _move(e) {
            if (!this._d) {
                return;
            }
            const dd = Math.round((e.clientX - this._d.x) / this._d.dw);
            if (this._d.mode === "move") {
                this.offset = Math.max(0, this._d.o + dd);
            } else if (this._d.mode === "l") {
                const newOffset = Math.max(
                    0,
                    Math.min(this._d.o + dd, this._d.o + this._d.du),
                );
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
            const changed =
                this.offset !== this._d.o || this.duration !== this._d.du;
            this._d = null;
            if (changed) {
                this.persist();
            }
        },
        persist() {
            patchJson(this.url, {
                start_date: this.addDays(this.fromIso, this.offset),
                due_date: this.addDays(
                    this.fromIso,
                    this.offset + this.duration,
                ),
            }).catch(() => {});
        },
        /** @param {string} iso @param {number} days */
        addDays(iso, days) {
            const d = new Date(iso + "T00:00:00");
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0, 10);
        },
        /** @param {string} iso */
        fmt(iso) {
            const p = iso.split("-");
            return p[2] + "." + p[1] + ".";
        },
    }));

    // Arbeitszeit-Modell-Dialog. days nach Punkt-Keys d1..d7 (CSP: kein days[iso]).
    /**
     * Ein Wochentag; Zahlenfelder werden per x-model zu Strings.
     * @typedef {Object} WsDay
     * @property {boolean} enabled
     * @property {string} mode
     * @property {string | number} hours
     * @property {string} start
     * @property {string} end
     * @property {string | number} break
     */
    Alpine.data("wsForm", () => ({
        type: "flextime",
        unit: "minutes",
        /** @type {Record<string, string | number>} weekly/daily/breakAfter/breakMin */
        d: { weekly: 0, daily: 0, breakAfter: 0, breakMin: 0 },
        /** @type {Record<string, WsDay>} */
        days: {},
        init() {
            const c = JSON.parse(this.$el.dataset.config || "{}");
            this.type = c.type || "flextime";
            this.unit = c.unit || "minutes";
            this.d = {
                weekly: c.weekly ?? 0,
                daily: c.daily ?? 0,
                breakAfter: c.breakAfter ?? 0,
                breakMin: c.breakMin ?? 0,
            };
            const src = c.days || {};
            /** @type {Record<string, WsDay>} */
            const days = {};
            for (let iso = 1; iso <= 7; iso++) {
                const v = src[iso] ?? src[String(iso)] ?? {};
                days["d" + iso] = {
                    enabled: false,
                    mode: "hours",
                    hours: "",
                    start: "",
                    end: "",
                    break: "",
                    ...v,
                };
            }
            this.days = days;
        },
        /** @param {number} iso */
        day(iso) {
            return this.days["d" + iso];
        },
        get unitLabel() {
            return this.unit === "hours" ? "Std." : "Min.";
        },
        get step() {
            return this.unit === "hours" ? "0.25" : "1";
        },
        /** @param {string} t */
        isType(t) {
            return this.type === t;
        },
        /** @param {string} u */
        unitClass(u) {
            return this.unit === u ? "btn-primary" : "btn-ghost";
        },
        /** @param {...string} ts */
        isTypeAny(...ts) {
            return ts.includes(this.type);
        },
        /** @param {number} iso @param {string} mode */
        dayModeIs(iso, mode) {
            return this.day(iso).mode === mode;
        },
        /** @param {number} iso */
        dayDisabled(iso) {
            return !this.day(iso).enabled;
        },
        /** @param {number} iso */
        dayEnabledValue(iso) {
            return this.day(iso).enabled ? "1" : "0";
        },
        /** @param {number} iso */
        dayRowClass(iso) {
            return this.day(iso).enabled ? "" : "opacity-50";
        },
        /** @param {string | number | undefined} v */
        parse(v) {
            const n = parseFloat(String(v).replace(",", "."));
            return isNaN(n) ? 0 : n;
        },
        /** @param {string | number} v */
        toMin(v) {
            const n = this.parse(v);
            return this.unit === "hours" ? Math.round(n * 60) : Math.round(n);
        },
        /** @param {number} iso */
        dayHours(iso) {
            const n = this.parse(this.day(iso)?.hours);
            return this.unit === "hours" ? n : +(n / 60).toFixed(4);
        },
        /** @param {number} iso */
        dayMinutes(iso) {
            const day = this.day(iso);
            if (!day || !day.enabled) {
                return 0;
            }
            if (day.mode === "times") {
                return Math.max(
                    0,
                    this.minutesBetween(day.start, day.end) -
                        (parseInt(String(day.break), 10) || 0),
                );
            }
            return this.toMin(day.hours);
        },
        /** @param {number} iso */
        dayMinutesFmt(iso) {
            return this.fmt(this.dayMinutes(iso));
        },
        /** @param {number} iso */
        dayMinutesLabel(iso) {
            return this.day(iso)?.enabled
                ? this.fmt(this.dayMinutes(iso))
                : "–";
        },
        /** @param {string} start @param {string} end */
        minutesBetween(start, end) {
            const re = /^\d{1,2}:\d{2}$/;
            if (!re.test(start || "") || !re.test(end || "")) {
                return 0;
            }
            const [sh, sm] = start.split(":").map((x) => parseInt(x, 10));
            const [eh, em] = end.split(":").map((x) => parseInt(x, 10));
            return Math.max(0, eh * 60 + em - (sh * 60 + sm));
        },
        /** @param {number} min */
        fmt(min) {
            const m = Math.max(0, Math.round(min));
            return (
                Math.floor(m / 60) +
                ":" +
                String(m % 60).padStart(2, "0") +
                " h"
            );
        },
        get weeklyTotalMinutes() {
            return [1, 2, 3, 4, 5, 6, 7].reduce(
                (sum, iso) => sum + this.dayMinutes(iso),
                0,
            );
        },
        get weeklyTotalFmt() {
            return this.fmt(this.weeklyTotalMinutes);
        },
        /** @param {string} u */
        switchTo(u) {
            if (u === this.unit) {
                return;
            }
            const conv = (/** @type {string | number} */ val) => {
                const mins =
                    this.unit === "hours"
                        ? this.parse(val) * 60
                        : this.parse(val);
                return u === "hours"
                    ? +(mins / 60).toFixed(2)
                    : Math.round(mins);
            };
            for (const k of Object.keys(this.d)) {
                this.d[k] = conv(this.d[k]);
            }
            for (const k of Object.keys(this.days)) {
                if (
                    this.days[k] &&
                    this.days[k].hours !== undefined &&
                    this.days[k].hours !== ""
                ) {
                    this.days[k].hours = conv(this.days[k].hours);
                }
            }
            this.unit = u;
        },
    }));

    // Liegenschafts-Picker (Customer → Site → Building → Floor → Room).
    /**
     * Picker-Daten aus components/facility-picker (IDs als int).
     * @typedef {Object} FacilityData
     * @property {Array<{id: number, name: string}>} customers
     * @property {Array<{id: number, name: string, customer_id: number | null}>} [foreignCustomers]
     * @property {Array<{id: number, name: string, customer_id: number | null}>} sites
     * @property {Array<{id: number, name: string, site_id: number | null}>} buildings
     * @property {Array<{id: number, label: string, level: number, building_id: number | null}>} floors
     * @property {Array<{id: number, name: string, floor_id: number | null, customer_id: number | null}>} rooms
     */
    Alpine.data("facilityPicker", () => ({
        /** @type {FacilityData} */
        data: {
            customers: [],
            foreignCustomers: [],
            sites: [],
            buildings: [],
            floors: [],
            rooms: [],
        },
        withRoom: false,
        withForeignCustomer: false,
        /** @type {number | null} */
        customer_id: null,
        /** @type {number | null} */
        foreign_customer_id: null,
        /** @type {number | null} */
        site_id: null,
        /** @type {number | null} */
        building_id: null,
        /** @type {number | null} */
        floor_id: null,
        /** @type {number | null} */
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
                /** @type {Array<[string, number | null]>} */
                const pairs = [
                    ["customerSelect", this.customer_id],
                    ["foreignSelect", this.foreign_customer_id],
                    ["siteSelect", this.site_id],
                    ["buildingSelect", this.building_id],
                    ["floorSelect", this.floor_id],
                    ["roomSelect", this.room_id],
                ];
                for (const [ref, value] of pairs) {
                    const el = /** @type {HTMLSelectElement | undefined} */ (this.$refs[ref]);
                    if (el) {
                        el.value = value == null ? "" : String(value);
                    }
                }
            });
        },
        autoSelectSingles() {
            if (
                this.customer_id != null &&
                this.site_id == null &&
                this.filteredSites.length === 1
            ) {
                this.site_id = this.filteredSites[0].id;
            }
            if (
                this.site_id != null &&
                this.building_id == null &&
                this.filteredBuildings.length === 1
            ) {
                this.building_id = this.filteredBuildings[0].id;
            }
            if (
                this.building_id != null &&
                this.floor_id == null &&
                this.filteredFloors.length === 1
            ) {
                this.floor_id = this.filteredFloors[0].id;
            }
            if (
                this.withRoom &&
                this.floor_id != null &&
                this.room_id == null &&
                this.filteredRooms.length === 1
            ) {
                this.room_id = this.filteredRooms[0].id;
            }
        },
        get filteredSites() {
            if (this.customer_id == null) {
                return this.data.sites;
            }
            return this.data.sites.filter(
                (s) =>
                    s.customer_id == null || s.customer_id === this.customer_id,
            );
        },
        get filteredForeignCustomers() {
            if (this.customer_id == null) {
                return [];
            }
            return (this.data.foreignCustomers ?? []).filter(
                (fc) => fc.customer_id === this.customer_id,
            );
        },
        get filteredBuildings() {
            if (this.site_id == null) {
                if (this.customer_id == null) {
                    return this.data.buildings;
                }
                /** @type {Set<number | null>} */
                const siteIds = new Set(this.filteredSites.map((s) => s.id));
                return this.data.buildings.filter((b) =>
                    siteIds.has(b.site_id),
                );
            }
            return this.data.buildings.filter(
                (b) => b.site_id === this.site_id,
            );
        },
        get filteredFloors() {
            if (this.building_id == null) {
                /** @type {Set<number | null>} */
                const bIds = new Set(this.filteredBuildings.map((b) => b.id));
                return this.data.floors.filter((f) => bIds.has(f.building_id));
            }
            return this.data.floors.filter(
                (f) => f.building_id === this.building_id,
            );
        },
        get filteredRooms() {
            let rooms = this.data.rooms;
            if (this.floor_id != null) {
                rooms = rooms.filter((r) => r.floor_id === this.floor_id);
            } else {
                const fIds = new Set(this.filteredFloors.map((f) => f.id));
                rooms = rooms.filter(
                    (r) => r.floor_id == null || fIds.has(r.floor_id),
                );
            }
            if (this.customer_id != null) {
                rooms = rooms.filter(
                    (r) =>
                        r.customer_id == null ||
                        r.customer_id === this.customer_id,
                );
            }
            return rooms;
        },
        get hasForeignCustomers() {
            return this.filteredForeignCustomers.length > 0;
        },
        // Anzeigename eines Geschosses — als Methode statt Template-Literal in
        // der Direktive (der @alpinejs/csp-Parser kennt keine Backticks).
        /** @param {{label: string, level: number}} f */
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
                const floor = this.data.floors.find(
                    (f) => f.id === this.floor_id,
                );
                if (floor) {
                    this.building_id = floor.building_id;
                }
            }
            if (this.building_id != null && this.site_id == null) {
                const building = this.data.buildings.find(
                    (b) => b.id === this.building_id,
                );
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
                const fc = (this.data.foreignCustomers ?? []).find(
                    (f) => f.id === this.foreign_customer_id,
                );
                if (!fc || fc.customer_id !== this.customer_id) {
                    this.foreign_customer_id = null;
                }
            }
            if (this.site_id != null) {
                const site = this.data.sites.find((s) => s.id === this.site_id);
                if (
                    !site ||
                    (site.customer_id != null &&
                        site.customer_id !== this.customer_id)
                ) {
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
                const b = this.data.buildings.find(
                    (x) => x.id === this.building_id,
                );
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
    /** @typedef {{id: string, name: string, color: string | null}} Tag */
    /** @typedef {{id: string | null, name: string, color: string | null, isNew: boolean, key: string}} SelectedTag */
    /**
     * data-config aus components/tag-picker.
     * @typedef {Object} TagPickerConfig
     * @property {Array<{id?: string | number | null, name?: string | null, color?: string | null}>} [all]
     * @property {Array<string | number>} [selectedIds]
     * @property {Array<string | number>} [recentIds]
     * @property {Array<string | number>} [initialNew]
     * @property {number} [quickLimit]
     * @property {boolean} [allowCreate]
     * @property {string | null} [suggestUrl]
     * @property {string | null} [textSelector]
     * @property {string | null} [customerSelector]
     */
    Alpine.data("tagPicker", () => {
        /** @type {Map<string, Tag>} */
        let byId = new Map();
        let newKey = 0;
        return {
            /** @type {Tag[]} */
            all: [],
            /** @type {string[]} */
            recentIds: [],
            quickLimit: 8,
            allowCreate: true,
            /** @type {SelectedTag[]} */
            selected: [],
            query: "",
            open: false,
            highlight: 0,
            // KI-Tagvorschläge (Feature 143, MVP-711): nur bei suggestUrl.
            /** @type {string | null} */
            suggestUrl: null,
            /** @type {string | null} */
            textSelector: null,
            /** @type {string | null} */
            customerSelector: null,
            /** @type {Tag[]} */
            suggestions: [],
            suggesting: false,
            suggestNotice: "",
            init() {
                /** @type {TagPickerConfig} */
                const cfg = JSON.parse(this.$el.dataset.config || "{}");
                this.suggestUrl = cfg.suggestUrl || null;
                this.textSelector = cfg.textSelector || null;
                this.customerSelector = cfg.customerSelector || null;
                this.all = (cfg.all ?? []).map((t) => ({
                    id: String(t.id ?? ""),
                    name: String(t.name ?? ""),
                    color: t.color ?? null,
                }));
                byId = new Map(this.all.map((t) => [t.id, t]));
                this.recentIds = (cfg.recentIds ?? []).map(String);
                this.quickLimit = cfg.quickLimit ?? 8;
                this.allowCreate = cfg.allowCreate !== false;
                const initialExisting = /** @type {Tag[]} */ (
                    (cfg.selectedIds ?? [])
                        .map((id) => byId.get(String(id)))
                        .filter(Boolean)
                ).map((t) => ({ ...t, isNew: false, key: "e" + t.id }));
                const initialNew = (cfg.initialNew ?? [])
                    .map((n) => String(n).trim())
                    .filter(Boolean)
                    .map((name) => ({
                        id: null,
                        name,
                        color: null,
                        isNew: true,
                        key: "n" + newKey++,
                    }));
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
                    ids: new Set(
                        this.selected.filter((t) => !t.isNew).map((t) => t.id),
                    ),
                    names: new Set(
                        this.selected.map((t) => t.name.toLowerCase()),
                    ),
                };
            },
            get quickPicks() {
                const ids = this.selectedKeyset.ids;
                const ordered = this.recentIds.length
                    ? /** @type {Tag[]} */ (this.recentIds.map((id) => byId.get(id)).filter(Boolean))
                    : this.all;
                return ordered
                    .filter((t) => !ids.has(t.id))
                    .slice(0, this.quickLimit);
            },
            get filtered() {
                const ids = this.selectedKeyset.ids;
                const q = this.query.trim().toLowerCase();
                const pool = this.all.filter((t) => !ids.has(t.id));
                const matches =
                    q === ""
                        ? pool
                        : pool.filter((t) => t.name.toLowerCase().includes(q));
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
                return (
                    this.open && (this.filtered.length > 0 || this.canCreate)
                );
            },
            get queryTrimmed() {
                return this.query.trim();
            },
            /** @param {SelectedTag} tag */
            chipClass(tag) {
                return tag.isNew ? "badge-success" : "badge-primary";
            },
            /** @param {SelectedTag} tag */
            chipStyle(tag) {
                return tag.color
                    ? "background-color:" +
                          tag.color +
                          ";border-color:" +
                          tag.color +
                          ";color:#fff"
                    : "";
            },
            /** @param {Tag} tag */
            dotStyle(tag) {
                return "background:" + tag.color;
            },
            /** @param {number} idx */
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
            /** @param {number} idx */
            setHighlight(idx) {
                this.highlight = idx;
            },
            /** @param {Tag | undefined} tag */
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
            // --- KI-Tagvorschläge (Feature 143): Katalog = bestehende Tags,
            // Antwort wird auf `all` gemappt, Unbekanntes verworfen; Übernahme
            // ist derselbe Weg wie die Schnellauswahl (nie Auto-Apply).
            get canSuggest() {
                return this.suggestUrl !== null;
            },
            get hasSuggestions() {
                return this.suggestions.length > 0;
            },
            get hasSuggestNotice() {
                return this.suggestNotice !== "";
            },
            /** @param {string | null} selector */
            formField(selector) {
                if (!selector) {
                    return null;
                }
                const form = this.$root.closest("form");
                const el = form ? form.querySelector(selector) : null;
                return el instanceof HTMLInputElement ||
                    el instanceof HTMLTextAreaElement ||
                    el instanceof HTMLSelectElement
                    ? el
                    : null;
            },
            async suggest() {
                if (!this.suggestUrl || this.suggesting) {
                    return;
                }
                const textField = this.formField(this.textSelector);
                const text = textField ? String(textField.value || "").trim() : "";
                if (text === "") {
                    this.suggestNotice = __("js.ai.tags_no_text");
                    return;
                }
                this.suggesting = true;
                this.suggestions = [];
                this.suggestNotice = __("js.ai.tags_loading");
                try {
                    /** @type {{text: string, customer_id?: string}} */
                    const body = { text };
                    const customerField = this.formField(this.customerSelector);
                    const customer = customerField ? String(customerField.value || "").trim() : "";
                    if (customer !== "") {
                        body.customer_id = customer;
                    }
                    const res = /** @type {import("../lib/http.js").JsonResult<{ message?: unknown, tags?: Array<{id?: string | number | null} | null> }>} */ (
                        await postJson(this.suggestUrl, body)
                    );
                    if (!res.ok) {
                        const message =
                            res.data && res.data.message
                                ? String(res.data.message)
                                : String(res.status);
                        this.suggestNotice = __("js.ai.tags_failed", { message });
                        return;
                    }
                    const ids = this.selectedKeyset.ids;
                    /** @type {Array<{id?: string | number | null} | null>} */
                    const rows =
                        res.data && Array.isArray(res.data.tags) ? res.data.tags : [];
                    this.suggestions = /** @type {Tag[]} */ (
                        rows
                            .map((t) => byId.get(String(t && t.id ? t.id : "")))
                            .filter(Boolean)
                    ).filter((t) => !ids.has(t.id));
                    this.suggestNotice = this.suggestions.length
                        ? ""
                        : __("js.ai.tags_none");
                } catch (e) {
                    this.suggestNotice = __("js.ai.tags_failed", {
                        message: e instanceof Error ? e.message : String(e),
                    });
                } finally {
                    this.suggesting = false;
                }
            },
            /** @param {Tag} tag */
            acceptSuggestion(tag) {
                this.addExisting(tag);
                this.suggestions = this.suggestions.filter((t) => t.id !== tag.id);
            },
            createNew() {
                const name = this.query.trim();
                if (name === "") {
                    return;
                }
                const ql = name.toLowerCase();
                const existing = this.all.find(
                    (t) => t.name.toLowerCase() === ql,
                );
                if (existing) {
                    this.addExisting(existing);
                    return;
                }
                if (this.selected.some((t) => t.name.toLowerCase() === ql)) {
                    this.resetInput();
                    return;
                }
                this.selected.push({
                    id: null,
                    name,
                    color: null,
                    isNew: true,
                    key: "n" + newKey++,
                });
                this.resetInput();
            },
            /** @param {SelectedTag} item */
            remove(item) {
                this.selected = this.selected.filter((t) => t.key !== item.key);
            },
            enterPressed() {
                const list = this.filtered;
                if (
                    list.length &&
                    this.highlight >= 0 &&
                    this.highlight < list.length
                ) {
                    this.addExisting(list[this.highlight]);
                } else if (this.canCreate) {
                    this.createNew();
                }
            },
            /** @param {number} dir */
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
    /** @typedef {import("signature_pad").default} SignaturePad */
    Alpine.data("signaturePad", () => ({
        /** @type {SignaturePad | null} */
        pad: null,
        isEmpty: true,
        /** @type {(() => void) | null} */
        resizeHandler: null,
        customerName: "",
        customerRole: "",
        customerEmail: "",
        init() {
            const d = this.$el.dataset;
            this.customerName = d.name || "";
            this.customerRole = d.role || "";
            this.customerEmail = d.email || "";

            // signature.js (Lazy-Entry) läuft als Modul erst NACH app.js/Alpine.start(),
            // in nachgeladenen Dialogen gar nicht — fehlt die Klasse, als eigenen Chunk nachladen.
            const ready = window.SignaturePad
                ? Promise.resolve(window.SignaturePad)
                : import("signature_pad").then((m) => (window.SignaturePad = m.default));
            ready
                .then((SignaturePadClass) => this.mount(SignaturePadClass))
                .catch((e) => console.error("[signature-pad] Laden fehlgeschlagen", e));
        },
        /** @param {typeof import("signature_pad").default} SignaturePadClass */
        mount(SignaturePadClass) {
            const c = /** @type {HTMLCanvasElement | undefined} */ (this.$refs.canvas);
            if (!c || this.pad || !this.$el.isConnected) {
                return;
            }
            this.pad = new SignaturePadClass(c, {
                penColor: "#111",
                backgroundColor: "rgba(255,255,255,0)",
            });
            // Der Listener lebt nur, solange das Pad lebt — this.pad ist dann gesetzt.
            this.pad.addEventListener("endStroke", () => {
                this.isEmpty = /** @type {SignaturePad} */ (this.pad).isEmpty();
                if (this.$refs.sigInput) {
                    /** @type {HTMLInputElement} */ (this.$refs.sigInput).value =
                        /** @type {SignaturePad} */ (this.pad).toDataURL("image/png");
                }
            });
            this.resizeHandler = () => this.resizeCanvas();
            window.addEventListener("resize", this.resizeHandler);
            requestAnimationFrame(() => this.resizeCanvas());
        },
        resizeCanvas() {
            const c = /** @type {HTMLCanvasElement | undefined} */ (this.$refs.canvas);
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
            /** @type {CanvasRenderingContext2D} */ (c.getContext("2d")).scale(ratio, ratio);
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
                /** @type {HTMLInputElement} */ (this.$refs.sigInput).value = "";
            }
        },
        /** @param {Event} e */
        prepare(e) {
            if (!this.pad || this.pad.isEmpty()) {
                e.preventDefault();
                return;
            }
            /** @type {HTMLInputElement} */ (this.$refs.sigInput).value = this.pad.toDataURL("image/png");
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
    /** @typedef {Record<string, unknown>} RepeaterItem Felder je nach data-template (Server-JSON). */
    Alpine.data("repeater", () => ({
        /** @type {RepeaterItem[]} */
        items: [],
        prefix: "items",
        /** @type {RepeaterItem} */
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
        /** @param {number} i */
        remove(i) {
            this.items.splice(i, 1);
        },
        /** @param {number} i @param {string} field */
        fieldName(i, field) {
            return this.prefix + "[" + i + "][" + field + "]";
        },
        // Andere benannte Zeilen (für "sichtbar wenn"-Referenzen) — als Methode
        // statt Arrow-Filter in der Direktive (CSP-Build-Parser kennt keine =>).
        /** @param {RepeaterItem} it */
        otherLabeledItems(it) {
            return this.items.filter((o) => o !== it && o.label);
        },
    }));

    // Zeitkorrektur-Antrag: Positionen wie „repeater“, das Ziel wählt man aus den
    // Buchungen/Anwesenheiten des Bezugstags statt über eine interne ID. Lädt neu,
    // wenn Bezugsdatum oder Mitarbeiter:in im Dialog wechseln.
    /**
     * Zeile aus time-approval/correction/_form_dialog ($itemTemplate).
     * @typedef {Object} CorrectionItem
     * @property {string} target_type Morph-Alias, Schlüssel in candidates
     * @property {string} target_id
     * @property {string} action
     * @property {string} before
     * @property {string} after
     */
    Alpine.data("correctionItems", () => ({
        /** @type {CorrectionItem[]} */
        items: [],
        prefix: "items",
        /** @type {Partial<CorrectionItem>} */
        template: {},
        /** @type {Record<string, Array<{id: string, label: string}>>} Morph-Alias → Buchungen */
        candidates: {},
        init() {
            const d = this.$el.dataset;
            this.items = JSON.parse(d.items || "[]");
            this.prefix = d.prefix || "items";
            this.template = JSON.parse(d.template || "{}");
            const form = this.$el.closest("form");
            if (form) {
                form.addEventListener("change", (e) => {
                    const name = e.target && /** @type {Element} */ (e.target).getAttribute("name");
                    if (name === "scope_date" || name === "user_id") {
                        this.load(form, d.targetsUrl);
                    }
                });
                this.load(form, d.targetsUrl);
            }
        },
        /** @param {HTMLFormElement} form @param {string | undefined} url */
        async load(form, url) {
            if (!url) {
                return;
            }
            const date = /** @type {HTMLInputElement | null} */ (form.querySelector('[name="scope_date"]'));
            const user = /** @type {FormControl | null} */ (form.querySelector('[name="user_id"]'));
            const params = new URLSearchParams({ date: date ? date.value : "" });
            if (user && user.value) {
                params.set("user", user.value);
            }
            try {
                const res = /** @type {import("../lib/http.js").JsonResult<Record<string, Array<{id: string, label: string}>>>} */ (
                    await getJson(url + "?" + params.toString())
                );
                this.candidates = res.ok && res.data ? res.data : {};
            } catch {
                this.candidates = {};
            }
        },
        /** @param {CorrectionItem} it */
        optionsFor(it) {
            return this.candidates[it.target_type] || [];
        },
        add() {
            this.items.push(JSON.parse(JSON.stringify(this.template)));
        },
        /** @param {number} i */
        remove(i) {
            this.items.splice(i, 1);
        },
        /** @param {number} i @param {string} field */
        fieldName(i, field) {
            return this.prefix + "[" + i + "][" + field + "]";
        },
    }));

    // Bedingungslogik Formular-Ausfüllen (Feature 032, Rang 33): spiegelt
    // FormFieldDefinition::isVisible clientseitig. Config via data-Attribute
    // (JSON): data-conditions {key: {field,op,value}}, data-initial {key: string}
    // — Objekt-Argumente via @js() wären im CSP-Build nicht auswertbar
    // (JSON.parse-Wrapper). Der Wrapper trackt Quelle-Werte generisch über name;
    // data-prefix wählt das Eingabe-Array (Standard `values`, Katalogvorlage `catalog`).
    Alpine.data("formFill", () => ({
        /** @type {Record<string, string>} */
        vals: {},
        /** @type {Record<string, {field?: string, op?: string, value?: string}>} FieldDefinition::$visibleIf */
        conditions: {},
        prefix: "values",
        init() {
            this.conditions = JSON.parse(this.$el.dataset.conditions || "{}");
            this.vals = Object.assign(
                {},
                JSON.parse(this.$el.dataset.initial || "{}"),
            );
            this.prefix = this.$el.dataset.prefix || "values";
        },
        /** @param {Event} e */
        track(e) {
            // Delegiert über alle Eingaben; checked wird nur bei type=checkbox gelesen.
            const t = /** @type {HTMLInputElement | null} */ (e.target);
            if (!t || !t.name) return;
            const m = String(t.name).match(/^([A-Za-z_]+)\[([^\]]+)\]$/);
            if (!m || m[1] !== this.prefix) return;
            this.vals[m[2]] =
                t.type === "checkbox" ? (t.checked ? "1" : "0") : t.value;
        },
        /** @param {string} key */
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
        /** @type {Array<{value: number | string}>} Minuten; x-model liefert Strings */
        items: [],
        init() {
            this.items = JSON.parse(this.$el.dataset.items || "[]").map(
                (/** @type {number} */ v) => ({ value: v }),
            );
        },
        add() {
            this.items.push({ value: 60 });
        },
        /** @param {number} i */
        remove(i) {
            this.items.splice(i, 1);
        },
        /** @param {number} i */
        fieldName(i) {
            return "reminder_offsets[" + i + "]";
        },
    }));

    // Krisenraum (MVP-963): Herzschlag alle 30 s, Liste der Anwesenden.
    Alpine.data("crisisPresence", () => ({
        /** @type {Array<{name: string}>} */
        people: [],
        /** @type {number | null} */
        timer: null,
        get isEmpty() {
            return this.people.length === 0;
        },
        init() {
            this.people = JSON.parse(this.$el.dataset.present || "[]");
            this.beat();
            this.timer = setInterval(() => this.beat(), 30000);
        },
        destroy() {
            clearInterval(this.timer ?? undefined);
        },
        beat() {
            // data-url setzt crisis/_room immer.
            /** @type {Promise<import("../lib/http.js").JsonResult<{ present?: Array<{name: string}> }>>} */ (
                postJson(/** @type {string} */ (this.$el.dataset.url))
            )
                .then((res) => {
                    this.people = res.data?.present ?? this.people;
                })
                .catch(() => {});
        },
    }));

    // Plugin-Verbindungstest (Health-Check) im Admin-Dialog.
    // _csrf bleibt in der Signatur (Blade übergibt positional), Token kommt
    // inzwischen zentral aus lib/http.js.
    Alpine.data("pluginHealthCheck", (
        /** @type {string} */ url,
        /** @type {string} */ _csrf,
        /** @type {string} */ failMsg,
    ) => ({
        testing: false,
        /** @type {{label?: string | null, message?: string, latency_ms?: number | null} | null} PluginHealth::toArray() + label */
        result: null,
        get idle() {
            return !this.testing;
        },
        get resultText() {
            const r = this.result;
            if (!r) {
                return "";
            }
            const lat = r.latency_ms != null ? " (" + r.latency_ms + "ms)" : "";
            return [r.label, r.message].filter(Boolean).join(" — ") + lat;
        },
        run() {
            this.testing = true;
            this.result = null;
            postJson(url)
                .then((res) => {
                    this.result = res.data ?? { message: failMsg };
                })
                .catch(() => {
                    this.result = { message: failMsg };
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
        /** @type {Record<string, string>} Farbname → Hex */
        colors: {},
        init() {
            const cfg = JSON.parse(this.$el.dataset.config || "{}");
            this.scheme = cfg.scheme ?? "light";
            this.colors = cfg.colors ?? {};
        },
        // Kontrastfarbe (dunkel/hell) zur übergebenen Hintergrundfarbe.
        /** @param {string | undefined} hex */
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
            s +=
                "--color-base-content:" +
                this.content(this.colors["base-100"]) +
                ";";
            [
                "primary",
                "secondary",
                "accent",
                "neutral",
                "info",
                "success",
                "warning",
                "error",
            ].forEach((k) => {
                s +=
                    "--color-" +
                    k +
                    "-content:" +
                    this.content(this.colors[k]) +
                    ";";
            });
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
        /** @type {number | null} */
        previewTimer: null,
        init() {
            this.content = this.$el.dataset.content || "service";
            this.schedulePreview();
        },
        // Delegierter change-Handler des Wrappers: reagiert nur auf das
        // "content"-Select im Formular.
        /** @param {Event & {target: FormControl | null}} event */
        onFormChange(event) {
            if (event.target && event.target.name === "content") {
                this.content = event.target.value;
            }
            // Änderungen INNERHALB der Vorschau (Ausschluss-Checkboxen) dürfen
            // keinen Reload auslösen — sonst verlöre die Auswahl ihren Stand.
            if (
                event.target &&
                event.target.closest("[data-invoice-preview]")
            ) {
                return;
            }
            this.schedulePreview();
        },
        schedulePreview() {
            const box = this.$root.querySelector("[data-invoice-preview]");
            if (!box) return;
            clearTimeout(this.previewTimer ?? undefined);
            this.previewTimer = setTimeout(() => {
                this.loadPreview();
            }, 350);
        },
        async loadPreview() {
            const box = /** @type {HTMLElement | null} */ (this.$root.querySelector("[data-invoice-preview]"));
            if (!box) return;
            const form = box.closest("form");
            if (!form || this.content !== "service") {
                clearHtml(box);
                return;
            }
            const fields = new FormData(form);
            const params = new URLSearchParams();
            [
                "customer_id",
                "project_id",
                "foreign_customer_id",
                "from",
                "to",
            ].forEach((name) => {
                const value = fields.get(name);
                if (value) params.set(name, String(value));
            });
            if (!params.get("customer_id")) {
                clearHtml(box);
                return;
            }
            try {
                // URLSearchParams-Body → fetch setzt den urlencoded
                // Content-Type selbst; Accept bleibt HTML (Fragment).
                const res = await request(box.dataset.url || "", {
                    method: "POST",
                    headers: { Accept: "text/html" },
                    body: params,
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
        /** @type {Array<{key: string, name: string}>} */
        customers: [],
        /** @type {Array<{sqid: string, label: string, ck: string}>} ck = Kundenschlüssel */
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
        /** @type {Record<number, {picked?: boolean, target?: string}>} */
        rows: {},
        init() {
            this.rows = JSON.parse(this.$el.dataset.rows || "{}");
        },
        /** @param {number} i */
        unpicked(i) {
            return !this.rows[i]?.picked;
        },
        // Abgewählt oder ohne Ziel → Allocation-Felder deaktivieren.
        /** @param {number} i */
        idle(i) {
            return !this.rows[i]?.picked || !this.rows[i]?.target;
        },
        /** @param {number} i */
        allocType(i) {
            return (this.rows[i]?.target || ":").split(":")[0];
        },
        /** @param {number} i */
        allocId(i) {
            return (this.rows[i]?.target || ":").split(":")[1];
        },
    }));

    // Zahlungsabgleich: Vorschlagsliste — Checkbox je Vorschlag schaltet die
    // zugehörigen Allocation-Felder frei; erster Vorschlag vorausgewählt.
    Alpine.data("reconciliationPick", () => ({
        /** @type {Record<number, boolean>} */
        picked: { 0: true },
        /** @param {number} i */
        unpicked(i) {
            return !this.picked[i];
        },
    }));

    // Dubletten-Listen (customers/projects duplicates): Sammel-Auswahl von
    // Paaren für die Bulk-Zusammenführung (ehemals Inline-x-data). Alle
    // Paar-Schlüssel via data-pairs (JSON) — Basis für „Alle auswählen".
    Alpine.data("pairSelection", () => ({
        /** @type {string[]} */
        selected: [],
        /** @type {string[]} "quelle:ziel"-Sqids */
        pairs: [],
        init() {
            this.pairs = JSON.parse(this.$el.dataset.pairs || "[]");
        },
        hasSelection() {
            return this.selected.length > 0;
        },
        allSelected() {
            return (
                this.pairs.length > 0 &&
                this.selected.length === this.pairs.length
            );
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
        /** @type {Record<string, Array<{id: string, name: string}>>} */
        foreignMap: {},
        /** @type {Record<string, Array<{id: string, name: string, fc: string | null}>>} */
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
            return (this.projectMap[this.customer] ?? []).filter(
                (p) => (p.fc ?? null) === fc,
            );
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
                .forEach((cb) => (/** @type {HTMLInputElement} */ (cb).checked = this.allChecked));
        },
        // Vorbefüllung Kunde → Fremdkunde: der Fremdkunden-Select wird erst
        // nach der Kundenwahl gerendert (x-for), daher Endkunde im nextTick.
        /** @param {string} customerSqid @param {string} foreignSqid */
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
        /** @param {Event & {currentTarget: HTMLElement | null}} evt */
        applySuggestion(evt) {
            const ds = evt.currentTarget?.dataset ?? {};
            const sqid = ds.suggestCustomer ?? "";
            if (!sqid) return;
            const fc = ds.suggestForeign ?? "";
            this.applyPreset(sqid, fc);
            this.$refs.list?.querySelectorAll("tr").forEach((tr) => {
                const cb = /** @type {HTMLInputElement | null} */ (tr.querySelector(
                    'input[type=checkbox][name="pending_ids[]"]',
                ));
                if (cb)
                    cb.checked =
                        tr.dataset.suggestCustomer === sqid &&
                        (tr.dataset.suggestForeign ?? "") === fc;
            });
        },
    }));

    // Fernwartungs-Inbox: Zuweisungsvorschlag einer unbekannten Geräte-ID.
    // data-suggest = {shared, customer(Sqid), asset(Sqid), matchcode}; apply()
    // befüllt nur die Formulare vor — gebucht wird weiterhin per Submit.
    Alpine.data("remoteSuggest", () => ({
        /** @type {{shared?: boolean, customer?: string | null, foreign?: string | null, asset?: string | null, matchcode?: string | null, matchcodeScope?: string | null}} */
        suggest: {},
        init() {
            this.suggest = JSON.parse(this.$el.dataset.suggest || "{}");
        },
        apply() {
            // $root statt $el: in Direktiven zeigt $el auf den Klick-Button.
            const root = this.$root;
            const s = this.suggest;
            const tabs = /** @type {NodeListOf<HTMLInputElement>} */ (root.querySelectorAll("input[type=radio].tab"));
            if (s.shared) {
                root.querySelectorAll(
                    'input[type=checkbox][name="shared_remote"]',
                ).forEach((cb) => (/** @type {HTMLInputElement} */ (cb).checked = true));
            }
            if (s.asset) {
                const sel = /** @type {HTMLSelectElement | null} */ (root.querySelector('select[name="asset_id"]'));
                if (sel) {
                    sel.value = s.asset;
                    sel.dispatchEvent(new Event("change", { bubbles: true }));
                }
                tabs[0]?.click();
            } else if (s.customer) {
                // Kunde + Endkunde über die remoteAssign-Komponente des
                // „Neues Gerät"-Formulars setzen (kaskadierende Selects).
                const form = root.querySelector('form[x-data="remoteAssign"]');
                const data = /** @type {{ applyPreset?: (customer: string, foreign: string) => void } | null} */ (
                    form && window.Alpine ? window.Alpine.$data(/** @type {HTMLElement} */ (form)) : null
                );
                if (data && typeof data.applyPreset === "function") {
                    data.applyPreset(s.customer, s.foreign || "");
                }
                tabs[1]?.click();
            }
            if (s.matchcode) {
                root.querySelectorAll('input[name="matchcode"]').forEach(
                    (inp) => (/** @type {HTMLInputElement} */ (inp).value = /** @type {string} */ (s.matchcode)),
                );
                root.querySelectorAll('input[name="matchcode_scope"]').forEach(
                    (inp) => (/** @type {HTMLInputElement} */ (inp).value = s.matchcodeScope || "customer"),
                );
            }
        },
    }));

    // Prüfungs-Player (Feature 149, MVP-783): fragenweise Anzeige, Zurück/
    // Überspringen, Merken, Zwischenspeichern je Antwort, Countdown mit
    // Abgabe bei 0. Ohne JavaScript bleiben alle Fragen sichtbar (x-show
    // greift erst nach dem Start) — der Server prüft Frist und Pflicht.
    // Optionen per data-options (Array-Argument → JSON.parse(…) im CSP-Build tot).
    Alpine.data("quizRunner", () => ({
        total: 0,
        single: false,
        allowBack: true,
        allowSkip: true,
        /** @type {number | null} */
        expiresAt: null,
        saveUrl: "",
        /** @type {number[]} */
        ids: [],
        /** @type {Set<number>} */
        answered: new Set(),
        /** @type {Set<number>} */
        flagged: new Set(),
        current: 0,
        remaining: "",
        submitted: false,
        /** @type {number | null} */
        timer: null,
        init() {
            const options = JSON.parse(this.$el.dataset.options || "{}");
            this.total = Number(options.total || 0);
            this.single = Boolean(options.single);
            this.allowBack = options.allowBack !== false;
            this.allowSkip = options.allowSkip !== false;
            this.expiresAt = options.expiresAt ? new Date(options.expiresAt).getTime() : null;
            this.saveUrl = options.saveUrl || "";
            this.ids = Array.isArray(options.ids) ? options.ids.map(Number) : [];
            this.answered = new Set((options.answered || []).map(Number));
            this.flagged = new Set((options.flagged || []).map(Number));
            if (this.single) {
                const firstOpen = this.ids.findIndex((id) => !this.answered.has(id));
                this.current = firstOpen >= 0 ? firstOpen : 0;
            }
            if (this.expiresAt) {
                this.tick();
                this.timer = setInterval(() => this.tick(), 1000);
            }
        },
        // Läuft nur, wenn init() expiresAt gesetzt hat.
        tick() {
            const left = Math.max(0, Math.floor((/** @type {number} */ (this.expiresAt) - Date.now()) / 1000));
            const p = (/** @type {number} */ n) => String(n).padStart(2, "0");
            this.remaining = p(Math.floor(left / 60)) + ":" + p(left % 60);
            if (left <= 0 && !this.submitted) {
                this.submitted = true;
                clearInterval(this.timer ?? undefined);
                const form = /** @type {HTMLFormElement} */ (this.$root);
                if (form && typeof form.requestSubmit === "function") {
                    form.requestSubmit();
                } else if (form) {
                    form.submit();
                }
            }
        },
        /** @param {number} index */
        isVisible(index) {
            return !this.single || index === this.current;
        },
        /** @param {number} index */
        isCurrent(index) {
            return this.single && index === this.current;
        },
        hasPrevious() {
            return this.allowBack && this.current > 0;
        },
        hasNext() {
            return this.current < this.total - 1;
        },
        /** @param {number} index */
        goTo(index) {
            if (!this.single) {
                const card = this.$root.querySelector('[data-quiz-question="' + this.ids[index] + '"]');
                if (card) card.scrollIntoView({ behavior: "smooth", block: "start" });
                return;
            }
            if (index < this.current && !this.allowBack) return;
            if (index >= 0 && index < this.total) this.current = index;
        },
        previous() {
            if (this.hasPrevious()) this.current--;
        },
        next() {
            if (this.hasNext()) this.current++;
        },
        skip() {
            if (this.allowSkip) this.next();
        },
        progressLabel() {
            return __("js.quiz.progress", { answered: this.answered.size, total: this.total });
        },
        /** @param {number} id */
        overviewClass(id) {
            if (this.flagged.has(id)) return "btn-warning";
            if (this.answered.has(id)) return "btn-success";
            return "btn-ghost";
        },
        // Antwort der Frage aus den Formularfeldern einsammeln — dieselbe
        // Struktur, die der Server beim Abgeben erwartet.
        /** @param {number} id */
        collect(id) {
            const prefix = "answers[" + id + "]";
            /** @type {Record<string, string | string[] | Record<string, string>>} */
            const payload = {};
            let hasValue = false;
            this.$root
                .querySelectorAll('[name^="' + prefix + '"]')
                .forEach((node) => {
                    // Auch select/textarea; checked wird nur bei radio/checkbox gelesen.
                    const field = /** @type {HTMLInputElement} */ (node);
                    if ((field.type === "radio" || field.type === "checkbox") && !field.checked) return;
                    const value = String(field.value ?? "").trim();
                    if (value === "") return;
                    const path = field.name
                        .slice(prefix.length)
                        .replace(/\]/g, "")
                        .split("[")
                        .filter((p) => p !== "");
                    const key = path[0];
                    if (!key) return;
                    if (field.name.endsWith("[]")) {
                        /** @type {string[]} */ (payload[key] ||= []).push(value);
                    } else if (path.length > 1) {
                        /** @type {Record<string, string>} */ (payload[key] ||= {})[path[1]] = value;
                    } else {
                        payload[key] = value;
                    }
                    hasValue = true;
                });
            return hasValue ? payload : null;
        },
        /** @param {number} id */
        async save(id) {
            if (!this.saveUrl) return;
            const payload = this.collect(id);
            const flag = /** @type {HTMLInputElement | null} */ (this.$root.querySelector('[data-quiz-flag="' + id + '"]'));
            const flagged = flag ? flag.checked : false;
            try {
                await patchJson(this.saveUrl, { question_id: id, payload, flagged });
                if (payload) this.answered.add(id); else this.answered.delete(id);
                if (flagged) this.flagged.add(id); else this.flagged.delete(id);
            } catch (e) {
                // Verbindungsabbruch: Formular behält die Eingabe, die Abgabe
                // schickt sie erneut — deshalb kein Alarm.
            }
        },
    }));
}

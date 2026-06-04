// Alpine.js-Komponente für den Arbeitszeit-Modell-Dialog.
// Global registriert, damit Blade sie via `x-data="wsForm(...)"` nutzen kann.
// Steuert: Arbeitszeit-Typ (schedule_type), Minuten/Stunden-Umschalter (Header)
// und die Pro-Wochentag-Vorgaben. Versteckte Felder posten weiterhin ganze
// Minuten bzw. (für den Stunden-Modus) Stunden, sodass die Server-Validierung
// unverändert rechnet.

window.wsForm = function (cfg) {
    const c = cfg || {};

    return {
        type: c.type || "flextime",
        unit: c.unit || "minutes",

        // Dauer-Rohwerte in der aktuell gewählten Einheit (Start: Minuten).
        d: {
            weekly: c.weekly ?? 0,
            daily: c.daily ?? 0,
            breakAfter: c.breakAfter ?? 0,
            breakMin: c.breakMin ?? 0,
        },

        // Pro-Wochentag-Konfiguration (ISO 1..7).
        days: c.days || {},

        get unitLabel() {
            return this.unit === "hours" ? "Std." : "Min.";
        },
        get step() {
            return this.unit === "hours" ? "0.25" : "1";
        },

        parse(v) {
            const n = parseFloat(String(v).replace(",", "."));
            return isNaN(n) ? 0 : n;
        },

        // Anzeigewert (aktuelle Einheit) → ganze Minuten (für die globalen Hidden-Felder).
        toMin(v) {
            const n = this.parse(v);
            return this.unit === "hours" ? Math.round(n * 60) : Math.round(n);
        },

        // Anzeigewert eines Tages → Stunden (für day_targets[iso][hours]; der
        // Server multipliziert mit 60). Bei Minuten-Einheit teilen wir durch 60.
        dayHours(iso) {
            const n = this.parse(this.days[iso]?.hours);
            return this.unit === "hours" ? n : +(n / 60).toFixed(4);
        },

        // Live-Tagessoll in Minuten für die Anzeige in der Wochentags-Tabelle.
        dayMinutes(iso) {
            const day = this.days[iso];
            if (!day || !day.enabled) return 0;
            if (day.mode === "times") {
                return Math.max(0, this.minutesBetween(day.start, day.end) - (parseInt(day.break, 10) || 0));
            }
            return this.toMin(day.hours);
        },

        minutesBetween(start, end) {
            const re = /^\d{1,2}:\d{2}$/;
            if (!re.test(start || "") || !re.test(end || "")) return 0;
            const [sh, sm] = start.split(":").map((x) => parseInt(x, 10));
            const [eh, em] = end.split(":").map((x) => parseInt(x, 10));
            return Math.max(0, eh * 60 + em - (sh * 60 + sm));
        },

        // Hübsche Stunden:Minuten-Ausgabe.
        fmt(min) {
            const m = Math.max(0, Math.round(min));
            return Math.floor(m / 60) + ":" + String(m % 60).padStart(2, "0") + " h";
        },

        get weeklyTotalMinutes() {
            return [1, 2, 3, 4, 5, 6, 7].reduce((sum, iso) => sum + this.dayMinutes(iso), 0);
        },

        // Einheit umschalten: alle in der Einheit anzeigbaren Dauerwerte umrechnen.
        switchTo(u) {
            if (u === this.unit) return;
            const conv = (val) => {
                const mins = this.unit === "hours" ? this.parse(val) * 60 : this.parse(val);
                return u === "hours" ? +(mins / 60).toFixed(2) : Math.round(mins);
            };
            for (const k of Object.keys(this.d)) this.d[k] = conv(this.d[k]);
            for (const iso of Object.keys(this.days)) {
                if (this.days[iso] && this.days[iso].hours !== undefined && this.days[iso].hours !== "") {
                    this.days[iso].hours = conv(this.days[iso].hours);
                }
            }
            this.unit = u;
        },
    };
};

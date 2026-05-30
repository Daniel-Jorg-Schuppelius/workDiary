// Alpine.js-Komponente für die Tag-Auswahl in Formularen.
// Kombiniert Schnellauswahl (zuletzt verwendete Tags), Volltextsuche über alle
// vorhandenen Tags und das Anlegen neuer Tags – alles in einer Eingabebox.
// Global registriert, damit Blade sie via `x-data="tagPicker(...)"` nutzen kann.
//
// Submit-Format (passt zu DiaryController::extractTagIds/extractNewTagNames):
//   - bestehende Tags: `tag_ids[]` mit opaker Sqid (String)
//   - neue Tags:       `new_tags` als kommaseparierter String

window.tagPicker = function (cfg) {
    const all = (cfg?.all ?? []).map((t) => ({
        id: String(t.id ?? ""),
        name: String(t.name ?? ""),
        color: t.color ?? null,
    }));
    const byId = new Map(all.map((t) => [t.id, t]));

    let newKey = 0;

    const initialExisting = (cfg?.selectedIds ?? [])
        .map((id) => byId.get(String(id)))
        .filter(Boolean)
        .map((t) => ({ ...t, isNew: false, key: "e" + t.id }));

    const initialNew = (cfg?.initialNew ?? [])
        .map((n) => String(n).trim())
        .filter(Boolean)
        .map((name) => ({
            id: null,
            name,
            color: null,
            isNew: true,
            key: "n" + newKey++,
        }));

    return {
        all,
        recentIds: (cfg?.recentIds ?? []).map(String),
        quickLimit: cfg?.quickLimit ?? 8,
        allowCreate: cfg?.allowCreate !== false,

        selected: [...initialExisting, ...initialNew],
        query: "",
        open: false,
        highlight: 0,

        // Hidden-Felder
        get existingIds() {
            return this.selected.filter((t) => !t.isNew).map((t) => t.id);
        },
        get newNames() {
            return this.selected.filter((t) => t.isNew).map((t) => t.name);
        },

        // Schon ausgewählt? (bestehende per ID, neue per Name)
        get selectedKeyset() {
            return {
                ids: new Set(
                    this.selected.filter((t) => !t.isNew).map((t) => t.id),
                ),
                names: new Set(this.selected.map((t) => t.name.toLowerCase())),
            };
        },

        // Schnellauswahl: zuletzt verwendete Tags (Fallback: erste alphabetisch)
        get quickPicks() {
            const { ids } = this.selectedKeyset;
            const ordered = this.recentIds.length
                ? this.recentIds.map((id) => byId.get(id)).filter(Boolean)
                : this.all;
            return ordered
                .filter((t) => !ids.has(t.id))
                .slice(0, this.quickLimit);
        },

        // Treffer im Suchfeld (ohne bereits ausgewählte)
        get filtered() {
            const { ids } = this.selectedKeyset;
            const q = this.query.trim().toLowerCase();
            const pool = this.all.filter((t) => !ids.has(t.id));
            const matches =
                q === ""
                    ? pool
                    : pool.filter((t) => t.name.toLowerCase().includes(q));
            return matches.slice(0, 8);
        },

        // Lässt sich die Eingabe als neuer Tag anlegen?
        get canCreate() {
            if (!this.allowCreate) return false;
            const q = this.query.trim();
            if (q === "") return false;
            const ql = q.toLowerCase();
            const { names } = this.selectedKeyset;
            if (names.has(ql)) return false;
            // Existiert bereits als Tag → lieber auswählen statt neu anlegen.
            return !this.all.some((t) => t.name.toLowerCase() === ql);
        },

        addExisting(tag) {
            if (!tag) return;
            if (this.selected.some((t) => !t.isNew && t.id === tag.id)) return;
            this.selected.push({ ...tag, isNew: false, key: "e" + tag.id });
            this.resetInput();
        },

        createNew() {
            const name = this.query.trim();
            if (name === "") return;
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
            this.selected.push({
                id: null,
                name,
                color: null,
                isNew: true,
                key: "n" + newKey++,
            });
            this.resetInput();
        },

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
};

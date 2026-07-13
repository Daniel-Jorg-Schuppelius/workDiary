// Millimeter-Editor für PDF-Dokumentdesign (Feature 076, MVP-297/302).
// Visuelle Bearbeitung (Ziehen/Skalieren) und numerische Millimeter-Eingaben
// sind gleichwertig; Pfeiltasten verschieben die ausgewählte Box (Shift = 5 mm).
// Speichert Entwürfe per fetch (PUT, JSON) und zeigt das Preflight-Ergebnis an.

const PAGE_W = 210;
const PAGE_H = 297;

function clamp(v, min, max) {
    return Math.min(max, Math.max(min, v));
}

function round1(v) {
    return Math.round(v * 10) / 10;
}

export function registerDesignEditor(Alpine) {
    // Config via data-config (JSON): { saveUrl, layout, blocks, tableStyle,
    // preflight, editable } — ein Inline-Objekt-Argument in x-data wäre im
    // @alpinejs/csp-Build nicht zuverlässig auswertbar (Stufe 2, MVP-346).
    Alpine.data("designEditor", () => ({
        saveUrl: null,
        layout: {},
        blocks: [],
        tableStyle: {},
        preflight: { errors: [], warnings: [] },
        editable: false,
        page: "first",
        selected: null,
        dirty: false,
        saving: false,
        message: null,
        drag: null,

        init() {
            const cfg = JSON.parse(this.$el.dataset.config || "{}");
            this.saveUrl = cfg.saveUrl ?? null;
            this.layout = cfg.layout ?? {};
            this.blocks = cfg.blocks ?? [];
            this.tableStyle = cfg.tableStyle ?? {};
            this.preflight = cfg.preflight ?? { errors: [], warnings: [] };
            this.editable = !!cfg.editable;
            this.layout.blocked_areas = this.layout.blocked_areas || [];
        },

        // ── Auswahl & Boxen ───────────────────────────────────────────────
        contentKey() {
            return this.page === "first" ? "content_first" : "content_following";
        },
        contentBox() {
            const m = this.layout[this.contentKey()];
            return {
                x: m.left,
                y: m.top,
                width: PAGE_W - m.left - m.right,
                height: PAGE_H - m.top - m.bottom,
            };
        },
        setContentBox(box) {
            const m = this.layout[this.contentKey()];
            m.left = round1(clamp(box.x, 0, PAGE_W - 10));
            m.top = round1(clamp(box.y, 0, PAGE_H - 10));
            m.right = round1(clamp(PAGE_W - box.x - box.width, 0, PAGE_W - 10));
            m.bottom = round1(clamp(PAGE_H - box.y - box.height, 0, PAGE_H - 10));
            this.dirty = true;
        },
        boxFor(key, index = null) {
            if (key === "content") return this.contentBox();
            if (key === "blocked") return this.layout.blocked_areas[index];
            return this.layout[key];
        },
        setBox(key, index, box) {
            if (key === "content") {
                this.setContentBox(box);
                return;
            }
            const target = key === "blocked" ? this.layout.blocked_areas[index] : this.layout[key];
            if (!target) return;
            target.x = round1(clamp(box.x, 0, PAGE_W - 1));
            target.y = round1(clamp(box.y, 0, PAGE_H - 1));
            if ("width" in target || "width" in box) target.width = round1(clamp(box.width, 5, PAGE_W));
            if ("height" in target) target.height = round1(clamp(box.height ?? target.height, 3, PAGE_H));
            this.dirty = true;
        },
        select(key, index = null) {
            this.selected = { key, index };
        },
        isSelected(key, index = null) {
            return this.selected && this.selected.key === key && this.selected.index === index;
        },

        // Skalierung: Box (mm) → CSS-Prozente der A4-Vorschaufläche.
        styleFor(key, index = null) {
            const b = this.boxFor(key, index);
            if (!b) return "display:none";
            const h = b.height ?? 8;
            return `left:${(b.x / PAGE_W) * 100}%;top:${(b.y / PAGE_H) * 100}%;width:${(b.width / PAGE_W) * 100}%;height:${(h / PAGE_H) * 100}%;`;
        },

        // ── Zeigerinteraktion (Ziehen/Skalieren) ──────────────────────────
        startDrag(event, key, index = null, mode = "move") {
            if (!this.editable) return;
            this.select(key, index);
            const rect = event.currentTarget.closest("[data-page-canvas]").getBoundingClientRect();
            const box = { ...this.boxFor(key, index) };
            box.height = box.height ?? 8;
            this.drag = { key, index, mode, box, rect, startX: event.clientX, startY: event.clientY };
            event.preventDefault();
        },
        onPointerMove(event) {
            if (!this.drag) return;
            const { rect, box, mode, key, index } = this.drag;
            const dx = ((event.clientX - this.drag.startX) / rect.width) * PAGE_W;
            const dy = ((event.clientY - this.drag.startY) / rect.height) * PAGE_H;
            const next = { ...box };
            if (mode === "move") {
                next.x = box.x + dx;
                next.y = box.y + dy;
            } else {
                next.width = box.width + dx;
                next.height = (box.height ?? 8) + dy;
            }
            this.setBox(key, index, next);
        },
        endDrag() {
            this.drag = null;
        },
        nudge(event) {
            if (!this.editable || !this.selected) return;
            const step = event.shiftKey ? 5 : 0.5;
            const delta = {
                ArrowLeft: [-step, 0],
                ArrowRight: [step, 0],
                ArrowUp: [0, -step],
                ArrowDown: [0, step],
            }[event.key];
            if (!delta) return;
            event.preventDefault();
            const box = { ...this.boxFor(this.selected.key, this.selected.index) };
            box.x += delta[0];
            box.y += delta[1];
            this.setBox(this.selected.key, this.selected.index, box);
        },

        // ── Optionale Bereiche ────────────────────────────────────────────
        toggleAddressWindow() {
            this.layout.address_window = this.layout.address_window
                ? null
                : { x: 25, y: 50, width: 85, height: 30 };
            this.dirty = true;
        },
        toggleSenderLine() {
            this.layout.sender_line = this.layout.sender_line
                ? null
                : { x: 25, y: 45, width: 85 };
            this.dirty = true;
        },
        addBlockedArea() {
            this.layout.blocked_areas.push({ page: "all", x: 150, y: 250, width: 40, height: 30, label: "" });
            this.select("blocked", this.layout.blocked_areas.length - 1);
            this.dirty = true;
        },
        removeBlockedArea(index) {
            this.layout.blocked_areas.splice(index, 1);
            this.selected = null;
            this.dirty = true;
        },
        blockedVisible(area) {
            return area.page === "all" || area.page === this.page;
        },

        markDirty() {
            this.dirty = true;
        },

        // ── Persistenz + Preflight ────────────────────────────────────────
        async save() {
            if (!this.editable || this.saving) return;
            this.saving = true;
            this.message = null;
            try {
                const response = await fetch(this.saveUrl, {
                    method: "PUT",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        layout: this.layout,
                        block_rules: this.blocks,
                        table_style: this.tableStyle,
                    }),
                });
                const data = await response.json();
                if (!response.ok) {
                    this.message = { tone: "error", text: data.message || "Fehler beim Speichern." };
                    return;
                }
                this.preflight = data.preflight;
                this.dirty = false;
                this.message = { tone: "success", text: null };
            } catch (e) {
                this.message = { tone: "error", text: String(e) };
            } finally {
                this.saving = false;
            }
        },
    }));
}

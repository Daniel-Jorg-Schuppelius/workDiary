// Millimeter-Editor für PDF-Dokumentdesign (Feature 076, MVP-297/302).
// Visuelle Bearbeitung (Ziehen/Skalieren) und numerische Millimeter-Eingaben
// sind gleichwertig; Pfeiltasten verschieben die ausgewählte Box (Shift = 5 mm).
// Speichert Entwürfe per fetch (PUT, JSON) und zeigt das Preflight-Ergebnis an.

import { putJson } from "./lib/http.js";
import { __ } from "./i18n.js";

// Seitenmaße kommen ab MVP-652 aus der Editor-Config (A4 hoch ODER quer).
const DEFAULT_PAGE_W = 210;
const DEFAULT_PAGE_H = 297;

/** @typedef {{top: number, right: number, bottom: number, left: number}} DesignMargins */
/** @typedef {{x: number, y: number, width: number, height: number}} DesignRect */
/** @typedef {{x: number, y: number, width: number}} DesignLine Absenderzeile, ohne Höhe */
/** @typedef {DesignRect & {page: "first" | "following" | "all", label: string}} DesignBlockedArea */
/** @typedef {{x: number, y: number, width: number, height?: number}} DesignBox Box beim Ziehen/Verschieben */
/** @typedef {"content" | "blocked" | "address_window" | "sender_line"} DesignBoxKey */

/**
 * Layout einer Profilversion (RenderProfileService::sanitizeLayout); init()
 * rüstet header/footer/typography/blocked_areas bei Altständen nach.
 * @typedef {Object} DesignLayout
 * @property {{format: string, min_edge_mm: number}} [page]
 * @property {DesignMargins} content_first
 * @property {DesignMargins} content_following
 * @property {DesignRect | null} [address_window]
 * @property {DesignLine | null} [sender_line]
 * @property {{note: string | null}} header
 * @property {{page_numbers: boolean, note: string | null}} footer
 * @property {DesignBlockedArea[]} blocked_areas
 * @property {{font_family: string | null, base_size_pt: number | null}} typography
 */

/** @typedef {{state: string, confirmed?: boolean}} DesignBlockRule */
/** @typedef {{preset?: string, use_brand_colors?: boolean, overrides?: Record<string, string | number | boolean | null>}} DesignTableStyle */
/** @typedef {{code: string, message: string, page?: string, block?: string}} DesignPreflightIssue */
/** @typedef {{ok?: boolean, errors: DesignPreflightIssue[], warnings: DesignPreflightIssue[]}} DesignPreflight */
/** @typedef {{header_text: string | null, footer_text: string | null}} DesignContentTexts */

/**
 * Editor-Config aus admin/document-design/editor (data-config). Leere
 * PHP-Arrays kommen als JSON-Array an, daher `| []` bei den Blockregeln.
 * @typedef {Object} DesignEditorConfig
 * @property {string} [saveUrl]
 * @property {DesignLayout} [layout]
 * @property {Record<string, DesignBlockRule> | []} [blocks]
 * @property {DesignTableStyle} [tableStyle]
 * @property {DesignPreflight} [preflight]
 * @property {boolean} [editable]
 * @property {boolean} [canInherit]
 * @property {string[] | null} [overrideSections]
 * @property {DesignContentTexts | null} [contentTexts]
 * @property {Record<string, string>} [blockLabels]
 * @property {string} [previewUrl]
 * @property {number} [pageW]
 * @property {number} [pageH]
 * @property {{first: string | null, following: string | null}} [assets]
 * @property {Record<string, string>} [assetPreviews]
 * @property {string | null} [baseName]
 */

/**
 * @typedef {Object} DesignDrag
 * @property {DesignBoxKey} key
 * @property {number | null} index
 * @property {string} mode
 * @property {DesignBox} box
 * @property {DOMRect} rect
 * @property {number} startX
 * @property {number} startY
 */

/**
 * @param {number} v
 * @param {number} min
 * @param {number} max
 */
function clamp(v, min, max) {
    return Math.min(max, Math.max(min, v));
}

/** @param {number} v */
function round1(v) {
    return Math.round(v * 10) / 10;
}

/** @param {import("alpinejs").Alpine} Alpine */
export function registerDesignEditor(Alpine) {
    // Config via data-config (JSON): { saveUrl, layout, blocks, tableStyle,
    // preflight, editable } — ein Inline-Objekt-Argument in x-data wäre im
    // @alpinejs/csp-Build nicht zuverlässig auswertbar (Stufe 2, MVP-346).
    Alpine.data("designEditor", () => ({
        /** @type {string | null} */
        saveUrl: null,
        // init() setzt das Layout aus der Config.
        layout: /** @type {DesignLayout} */ ({}),
        /** @type {Record<string, DesignBlockRule> | []} */
        blocks: [],
        /** @type {DesignTableStyle} */
        tableStyle: {},
        /** @type {DesignPreflight} */
        preflight: { errors: [], warnings: [] },
        editable: false,
        page: "first",
        /** @type {{key: DesignBoxKey, index: number | null} | null} */
        selected: null,
        dirty: false,
        saving: false,
        /** @type {{tone: string, text: string | null} | null} */
        message: null,
        /** @type {DesignDrag | null} */
        drag: null,
        // Vererbung vom CI-Basisdesign (#83): nur wenn canInherit; die
        // Overrides-Flags markieren, welche Sektionen dieses Profil selbst trägt.
        canInherit: false,
        inheritEnabled: false,
        /** @type {Record<string, boolean>} */
        overrides: {
            margins: true,
            address: true,
            blocked_areas: true,
            footer: true,
            typography: true,
            assets: true,
            block_rules: true,
            table_style: true,
            content_texts: true,
        },
        /** @type {Record<string, string>} */
        blockLabels: {},
        // Kopf-/Fußtexte des Belegs (MVP-651, vormals invoice_templates).
        /** @type {DesignContentTexts} */
        contentTexts: { header_text: null, footer_text: null },
        // Eingebettete PDF-Vorschau (#83): Art/Szenario umschaltbar; tick
        // erzwingt das Neuladen des iframes nach dem Speichern.
        pageW: DEFAULT_PAGE_W,
        pageH: DEFAULT_PAGE_H,
        /** @type {string | null} */
        previewUrl: null,
        previewKind: "invoice",
        previewScenario: "standard",
        previewTick: 0,
        // Reiter der rechten Spalte: Aussehen | Layout | Inhalte | Freigabe.
        tab: "appearance",
        // Firmenbogen-Zuordnung: Auswahl wirkt sofort auf den Canvas
        // (assetPreviews: sqid → Vorschau-URL), gespeichert mit dem Entwurf.
        assets: { first: "", following: "" },
        /** @type {Record<string, string>} */
        assetPreviews: {},
        // Name des Basisprofils (nur Anzeige; kommt aus data-config).
        baseName: "",

        init() {
            /** @type {DesignEditorConfig} */
            const cfg = JSON.parse(this.$el.dataset.config || "{}");
            this.saveUrl = cfg.saveUrl ?? null;
            // Die View liefert immer ein Layout; `{}` ist nur der Rückfall.
            this.layout = cfg.layout ?? /** @type {DesignLayout} */ ({});
            this.blocks = cfg.blocks ?? [];
            this.tableStyle = cfg.tableStyle ?? {};
            this.preflight = cfg.preflight ?? { errors: [], warnings: [] };
            this.editable = !!cfg.editable;
            this.layout.blocked_areas = this.layout.blocked_areas || [];
            // Bestandsversionen ohne Typografie-Sektion (#83) nachrüsten.
            this.layout.typography = this.layout.typography || {
                font_family: null,
                base_size_pt: null,
            };
            this.layout.header = this.layout.header || { note: null };
            this.layout.footer = this.layout.footer || {
                page_numbers: false,
                note: null,
            };
            this.blockLabels = cfg.blockLabels ?? {};
            this.baseName = cfg.baseName ?? "";
            this.canInherit = !!cfg.canInherit;
            let sections = cfg.overrideSections;
            this.inheritEnabled = this.canInherit && Array.isArray(sections);
            // inheritEnabled schließt Array.isArray(sections) ein.
            if (this.inheritEnabled) {
                // Bestandsdaten: Sammel-Override 'layout' → feine Layout-Gruppen.
                if (/** @type {string[]} */ (sections).includes("layout")) {
                    sections = /** @type {string[]} */ (sections)
                        .filter((key) => key !== "layout")
                        .concat([
                            "margins",
                            "address",
                            "blocked_areas",
                            "footer",
                            "typography",
                        ]);
                }
                for (const key of Object.keys(this.overrides)) {
                    this.overrides[key] = /** @type {string[]} */ (sections).includes(key);
                }
            }
            this.previewUrl = cfg.previewUrl ?? null;
            this.pageW = cfg.pageW ?? DEFAULT_PAGE_W;
            this.pageH = cfg.pageH ?? DEFAULT_PAGE_H;
            this.contentTexts = cfg.contentTexts ?? {
                header_text: null,
                footer_text: null,
            };
            this.assets = {
                first: cfg.assets?.first ?? "",
                following: cfg.assets?.following ?? "",
            };
            this.assetPreviews = cfg.assetPreviews ?? {};
            // Direktsprung, z. B. „#tab-release" aus der Einstiegs-Checkliste.
            const hashTab = (window.location.hash || "").replace("#tab-", "");
            if (
                ["appearance", "layout", "content", "release"].includes(hashTab)
            ) {
                this.tab = hashTab;
            }
            // Der CSP-Build verweigert Direktiven auf <iframe> (:src warf) —
            // die Vorschau-URL setzt deshalb dieser Effekt.
            const frame = /** @type {HTMLIFrameElement | null} */ (this.$el.querySelector("iframe[data-design-preview]"));
            if (frame) {
                Alpine.effect(() => {
                    const src = this.previewSrc();
                    if (src) frame.src = src;
                });
            }
        },

        /** @param {string} name */
        setTab(name) {
            this.tab = name;
        },
        // Vorschau-URL des gewählten Bogens je Seitenrolle ("" = kein Bogen).
        /** @param {"first" | "following"} role */
        assetPreviewSrc(role) {
            const key = this.assets[role];
            return key && this.assetPreviews[key]
                ? this.assetPreviews[key]
                : "";
        },

        // Sektion wirksam aus diesem Profil (nicht geerbt)?
        /** @param {string} key */
        sectionOwn(key) {
            return !this.inheritEnabled || !!this.overrides[key];
        },
        overrideList() {
            return Object.keys(this.overrides).filter(
                (key) => this.overrides[key],
            );
        },
        previewSrc() {
            if (!this.previewUrl) return "";
            return `${this.previewUrl}?kind=${encodeURIComponent(this.previewKind)}&scenario=${encodeURIComponent(this.previewScenario)}&t=${this.previewTick}`;
        },
        reloadPreview() {
            this.previewTick++;
        },
        // Effektive Vererbungsquelle als Kurzfassung an der Vorschau. Der
        // Name kommt aus der Konfiguration, nicht aus dem Alpine-Ausdruck
        // (Sicherheitsaudit 2026-09-17, alpine-1).
        get inheritanceSummary() {
            const total = Object.keys(this.overrides).length;
            const own = this.overrideList().length;
            return __("js.design.inheritance", {
                base: this.baseName,
                inherited: total - own,
                total,
                own,
            });
        },
        // Als „bereits auf dem Firmenbogen" deklarierte Blöcke (nicht gedruckt).
        letterheadBlockLabels() {
            return Object.entries(this.blocks || {})
                .filter(
                    ([, rule]) =>
                        rule && rule.state === "provided_by_letterhead",
                )
                .map(([key]) => this.blockLabels[key] ?? key);
        },

        // ── Auswahl & Boxen ───────────────────────────────────────────────
        contentKey() {
            return this.page === "first"
                ? "content_first"
                : "content_following";
        },
        contentBox() {
            const m = this.layout[this.contentKey()];
            return {
                x: m.left,
                y: m.top,
                width: this.pageW - m.left - m.right,
                height: this.pageH - m.top - m.bottom,
            };
        },
        /** @param {DesignRect} box */
        setContentBox(box) {
            const m = this.layout[this.contentKey()];
            m.left = round1(clamp(box.x, 0, this.pageW - 10));
            m.top = round1(clamp(box.y, 0, this.pageH - 10));
            m.right = round1(
                clamp(this.pageW - box.x - box.width, 0, this.pageW - 10),
            );
            m.bottom = round1(
                clamp(this.pageH - box.y - box.height, 0, this.pageH - 10),
            );
            this.dirty = true;
        },
        /**
         * @param {DesignBoxKey} key
         * @param {number | null} [index] gesetzt bei "blocked"
         * @returns {DesignBox | null | undefined}
         */
        boxFor(key, index = null) {
            if (key === "content") return this.contentBox();
            if (key === "blocked") return this.layout.blocked_areas[/** @type {number} */ (index)];
            return this.layout[key];
        },
        /**
         * @param {DesignBoxKey} key
         * @param {number | null} index gesetzt bei "blocked"
         * @param {DesignBox} box
         */
        setBox(key, index, box) {
            if (key === "content") {
                // Inhaltsboxen tragen immer eine Höhe (contentBox(), startDrag()).
                this.setContentBox(/** @type {DesignRect} */ (box));
                return;
            }
            const target =
                key === "blocked"
                    ? this.layout.blocked_areas[/** @type {number} */ (index)]
                    : this.layout[key];
            if (!target) return;
            target.x = round1(clamp(box.x, 0, this.pageW - 1));
            target.y = round1(clamp(box.y, 0, this.pageH - 1));
            if ("width" in target || "width" in box)
                target.width = round1(clamp(box.width, 5, this.pageW));
            // Für die Absenderzeile (ohne height im Typ) liefert `in` unknown; gespeichert sind Zahlen.
            if ("height" in target)
                target.height = round1(
                    clamp(/** @type {number} */ (box.height ?? target.height), 3, this.pageH),
                );
            this.dirty = true;
        },
        /**
         * @param {DesignBoxKey} key
         * @param {number | null} [index]
         */
        select(key, index = null) {
            this.selected = { key, index };
        },
        /**
         * @param {DesignBoxKey} key
         * @param {number | null} [index]
         */
        isSelected(key, index = null) {
            return (
                this.selected &&
                this.selected.key === key &&
                this.selected.index === index
            );
        },

        // Skalierung: Box (mm) → CSS-Prozente der A4-Vorschaufläche.
        /**
         * @param {DesignBoxKey} key
         * @param {number | null} [index]
         */
        styleFor(key, index = null) {
            const b = this.boxFor(key, index);
            if (!b) return "display:none";
            const h = b.height ?? 8;
            return `left:${(b.x / this.pageW) * 100}%;top:${(b.y / this.pageH) * 100}%;width:${(b.width / this.pageW) * 100}%;height:${(h / this.pageH) * 100}%;`;
        },

        // ── Zeigerinteraktion (Ziehen/Skalieren) ──────────────────────────
        /**
         * @param {PointerEvent & {currentTarget: HTMLElement}} event
         * @param {DesignBoxKey} key
         * @param {number | null} [index]
         * @param {string} [mode]
         */
        startDrag(event, key, index = null, mode = "move") {
            if (!this.editable) return;
            this.select(key, index);
            // Die Griffe liegen immer innerhalb der Seitenfläche.
            const rect = /** @type {Element} */ (
                event.currentTarget.closest("[data-page-canvas]")
            ).getBoundingClientRect();
            // Ziehen startet nur an einer angezeigten, also vorhandenen Box.
            const box = /** @type {DesignBox} */ ({ ...this.boxFor(key, index) });
            box.height = box.height ?? 8;
            this.drag = {
                key,
                index,
                mode,
                box,
                rect,
                startX: event.clientX,
                startY: event.clientY,
            };
            event.preventDefault();
        },
        /** @param {PointerEvent} event */
        onPointerMove(event) {
            if (!this.drag) return;
            const { rect, box, mode, key, index } = this.drag;
            const dx =
                ((event.clientX - this.drag.startX) / rect.width) * this.pageW;
            const dy =
                ((event.clientY - this.drag.startY) / rect.height) * this.pageH;
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
        /** @param {KeyboardEvent} event */
        nudge(event) {
            if (!this.editable || !this.selected) return;
            const step = event.shiftKey ? 5 : 0.5;
            const delta = /** @type {Record<string, [number, number] | undefined>} */ ({
                ArrowLeft: [-step, 0],
                ArrowRight: [step, 0],
                ArrowUp: [0, -step],
                ArrowDown: [0, step],
            })[event.key];
            if (!delta) return;
            event.preventDefault();
            // Ausgewählt ist nur eine vorhandene Box.
            const box = /** @type {DesignBox} */ ({
                ...this.boxFor(this.selected.key, this.selected.index),
            });
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
            this.layout.blocked_areas.push({
                page: "all",
                x: 150,
                y: 250,
                width: 40,
                height: 30,
                label: "",
            });
            this.select("blocked", this.layout.blocked_areas.length - 1);
            this.dirty = true;
        },
        /** @param {number} index */
        removeBlockedArea(index) {
            this.layout.blocked_areas.splice(index, 1);
            this.selected = null;
            this.dirty = true;
        },
        /** @param {DesignBlockedArea} area */
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
                // Die View setzt saveUrl immer.
                const response = await putJson(/** @type {string} */ (this.saveUrl), {
                    layout: this.layout,
                    block_rules: this.blocks,
                    table_style: this.tableStyle,
                    content_texts: this.contentTexts,
                    first_asset: this.assets.first || "",
                    following_asset: this.assets.following || "",
                    ...(this.canInherit
                        ? {
                              override_sections: this.inheritEnabled
                                  ? this.overrideList()
                                  : null,
                          }
                        : {}),
                });
                const data = /** @type {{saved?: boolean, preflight: DesignPreflight, message?: string}} */ (response.data ?? {});
                if (!response.ok) {
                    this.message = {
                        tone: "error",
                        text: data.message || "Fehler beim Speichern.",
                    };
                    return;
                }
                this.preflight = data.preflight;
                this.dirty = false;
                this.message = { tone: "success", text: null };
                this.previewTick++;
            } catch (e) {
                this.message = { tone: "error", text: String(e) };
            } finally {
                this.saving = false;
            }
        },
    }));
}

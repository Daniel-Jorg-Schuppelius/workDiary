/*
 * zone-editor.js — Zonen-Editor des Rechnungseingangs (Phase 151, MVP-1220).
 *
 * Wurzel: <form data-zone-editor data-config='{pages, boxes, zones, fields, previewUrl}'> mit
 *   - [data-zone-pages]      Container für Seitenbilder samt Overlay
 *   - [data-zone-field]      Select des Feldes, für das gezeichnet wird
 *   - [data-zone-show-boxes] Checkbox: Wortboxen einblenden
 *   - [data-zone-list]       Liste der Zonen mit Vorschauwert
 *   - [data-zone-input]      verstecktes Feld, das beim Absenden die Zonen als JSON trägt
 *
 * Koordinaten sind relativ zur Seite (0–1); gezeichnet wird mit Pointer-Events,
 * die Vorschau fragt den Server (postJson). Kein innerHTML, keine Inline-Handler.
 */

import { __ } from "./i18n.js";
import { postJson } from "./lib/http.js";

/**
 * @typedef {{ x: number, y: number, w: number, h: number }} Rect
 * @typedef {{ page: number, x: number, y: number, w: number, h: number, text: string }} WordBox
 * @typedef {{ field: string, page: number, x: number, y: number, w: number, h: number, source?: string, confirmed?: boolean }} Zone
 * @typedef {{ pages: { page: number, url: string }[], boxes: WordBox[], zones: Zone[], fields: Record<string, string>, previewUrl: string }} ZoneEditorConfig
 * @typedef {{ raw: string, value: string | null }} PreviewResult
 */

/**
 * @param {HTMLFormElement} root
 */
const init = (root) => {
    if (!root.dataset.config) return;
    /** @type {ZoneEditorConfig} */
    const cfg = JSON.parse(root.dataset.config);
    const pagesEl = /** @type {HTMLElement | null} */ (root.querySelector("[data-zone-pages]"));
    const fieldEl = /** @type {HTMLSelectElement | null} */ (root.querySelector("[data-zone-field]"));
    const showBoxesEl = /** @type {HTMLInputElement | null} */ (root.querySelector("[data-zone-show-boxes]"));
    const listEl = /** @type {HTMLElement | null} */ (root.querySelector("[data-zone-list]"));
    const inputEl = /** @type {HTMLInputElement | null} */ (root.querySelector("[data-zone-input]"));
    if (!pagesEl || !fieldEl || !listEl || !inputEl) return;

    /** @type {Zone[]} */
    let zones = cfg.zones.map((z) => ({ ...z }));
    /** @type {Record<string, string>} */
    const values = {};
    /** @type {{ page: number, x0: number, y0: number, x1: number, y1: number } | null} */
    let draft = null;
    /** @type {Map<number, HTMLElement>} */
    const overlays = new Map();

    /**
     * @param {HTMLElement} el
     * @param {Rect} r
     */
    const place = (el, r) => {
        el.style.left = `${r.x * 100}%`;
        el.style.top = `${r.y * 100}%`;
        el.style.width = `${r.w * 100}%`;
        el.style.height = `${r.h * 100}%`;
    };

    /**
     * @param {PointerEvent} event
     * @param {HTMLElement} overlay
     */
    const relative = (event, overlay) => {
        const rect = overlay.getBoundingClientRect();
        return {
            x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
            y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
        };
    };

    const serialize = () => {
        inputEl.value = JSON.stringify(zones.map(({ field, page, x, y, w, h }) => ({ field, page, x, y, w, h })));
    };

    const renderList = () => {
        while (listEl.firstChild) listEl.removeChild(listEl.firstChild);
        for (const zone of zones) {
            const li = document.createElement("li");
            li.className = "flex items-center justify-between gap-2 rounded bg-base-200 px-2 py-1";
            const text = document.createElement("span");
            const label = document.createElement("strong");
            label.textContent = cfg.fields[zone.field] ?? zone.field;
            text.appendChild(label);
            text.appendChild(document.createTextNode(": " + (values[zone.field] ?? __("js.zone_editor.no_value"))));
            if (zone.source === "learned" && !zone.confirmed) {
                const hint = document.createElement("span");
                hint.className = "ml-1 text-xs text-muted";
                hint.textContent = __("js.zone_editor.learned");
                text.appendChild(hint);
            }
            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "btn btn-ghost btn-xs";
            remove.textContent = __("js.zone_editor.remove");
            remove.addEventListener("click", () => {
                zones = zones.filter((z) => z.field !== zone.field);
                delete values[zone.field];
                renderZones();
            });
            li.append(text, remove);
            listEl.appendChild(li);
        }
        serialize();
    };

    const renderZones = () => {
        for (const [page, overlay] of overlays) {
            overlay.querySelectorAll("[data-zone-rect]").forEach((el) => el.remove());
            for (const zone of zones) {
                if (zone.page !== page) continue;
                const el = document.createElement("div");
                el.dataset.zoneRect = zone.field;
                el.className = "pointer-events-none absolute border-2 border-primary bg-primary/10" + (zone.source === "learned" && !zone.confirmed ? " border-dashed" : "");
                place(el, zone);
                const badge = document.createElement("span");
                badge.className = "badge badge-primary badge-xs absolute -top-4 left-0 whitespace-nowrap";
                badge.textContent = cfg.fields[zone.field] ?? zone.field;
                el.appendChild(badge);
                overlay.appendChild(el);
            }
        }
        renderList();
    };

    /** @param {Zone} zone */
    const preview = async (zone) => {
        const res = /** @type {import("./lib/http.js").JsonResult<PreviewResult>} */ (
            await postJson(cfg.previewUrl, { field: zone.field, page: zone.page, x: zone.x, y: zone.y, w: zone.w, h: zone.h })
        );
        values[zone.field] = res.ok && res.data ? (res.data.value ?? (res.data.raw ? `? ${res.data.raw}` : __("js.zone_editor.no_value"))) : __("js.zone_editor.preview_failed");
        renderList();
    };

    const toggleBoxes = () => {
        const show = showBoxesEl ? showBoxesEl.checked : true;
        for (const overlay of overlays.values()) {
            overlay.querySelectorAll("[data-word-box]").forEach((el) => {
                /** @type {HTMLElement} */ (el).classList.toggle("hidden", !show);
            });
        }
    };

    for (const page of cfg.pages) {
        const wrap = document.createElement("div");
        wrap.className = "relative w-full overflow-hidden rounded-box border border-base-300 bg-base-200";
        const img = document.createElement("img");
        img.src = page.url;
        img.alt = `${__("js.zone_editor.page")} ${page.page}`;
        img.className = "block w-full";
        img.draggable = false;
        const overlay = document.createElement("div");
        overlay.className = "absolute inset-0 cursor-crosshair";
        for (const box of cfg.boxes) {
            if (box.page !== page.page) continue;
            const el = document.createElement("div");
            el.dataset.wordBox = "";
            el.className = "pointer-events-none absolute border border-info/40";
            el.title = box.text;
            place(el, box);
            overlay.appendChild(el);
        }
        const draftEl = document.createElement("div");
        draftEl.className = "pointer-events-none absolute hidden border-2 border-secondary bg-secondary/10";
        overlay.appendChild(draftEl);

        overlay.addEventListener("pointerdown", (event) => {
            const p = relative(event, overlay);
            draft = { page: page.page, x0: p.x, y0: p.y, x1: p.x, y1: p.y };
            overlay.setPointerCapture(event.pointerId);
            draftEl.classList.remove("hidden");
            place(draftEl, { x: p.x, y: p.y, w: 0, h: 0 });
        });
        overlay.addEventListener("pointermove", (event) => {
            if (!draft || draft.page !== page.page) return;
            const p = relative(event, overlay);
            draft.x1 = p.x;
            draft.y1 = p.y;
            place(draftEl, { x: Math.min(draft.x0, draft.x1), y: Math.min(draft.y0, draft.y1), w: Math.abs(draft.x1 - draft.x0), h: Math.abs(draft.y1 - draft.y0) });
        });
        overlay.addEventListener("pointerup", (event) => {
            if (!draft || draft.page !== page.page) return;
            const p = relative(event, overlay);
            const zone = /** @type {Zone} */ ({
                field: fieldEl.value,
                page: page.page,
                x: Math.min(draft.x0, p.x),
                y: Math.min(draft.y0, p.y),
                w: Math.abs(p.x - draft.x0),
                h: Math.abs(p.y - draft.y0),
                source: "manual",
                confirmed: true,
            });
            draft = null;
            draftEl.classList.add("hidden");
            // Ein Klick ohne Ziehen ist keine Zone.
            if (zone.w < 0.005 || zone.h < 0.003) return;
            zones = [...zones.filter((z) => z.field !== zone.field), zone];
            renderZones();
            void preview(zone);
        });
        wrap.append(img, overlay);
        pagesEl.appendChild(wrap);
        overlays.set(page.page, overlay);
    }

    showBoxesEl?.addEventListener("change", toggleBoxes);
    root.addEventListener("submit", serialize);
    renderZones();
    for (const zone of zones) void preview(zone);
};

const initAll = (scope = document) => {
    /** @type {NodeListOf<HTMLFormElement>} */ (scope.querySelectorAll("form[data-zone-editor]")).forEach((root) => init(root));
};

document.addEventListener("DOMContentLoaded", () => initAll());
document.addEventListener("livewire:navigated", () => initAll());

export { init, initAll };

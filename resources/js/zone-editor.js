/*
 * zone-editor.js — Zonen-Editor des Rechnungseingangs (Phase 151, MVP-1220; Anker und Probelauf seit Phase 155).
 *
 * Wurzel: <form data-zone-editor data-config='{pages, boxes, zones, fields, scopes, previewUrl, trialUrl}'> mit
 *   - [data-zone-pages]      Container für Seitenbilder samt Overlay
 *   - [data-zone-field]      Select des Feldes, für das gezeichnet wird
 *   - [data-zone-show-boxes] Checkbox: Wortboxen einblenden
 *   - [data-zone-list]       Liste der Zonen mit Anker, Bereich, Zeilenmuster und Vorschauwert
 *   - [data-zone-input]      verstecktes Feld, das beim Absenden die Zonen als JSON trägt
 *   - [data-zone-trial]      Knopf „Probelauf“, [data-zone-trial-result] seine Ausgabe
 *
 * Koordinaten sind relativ zur Seite (0–1); gezeichnet wird mit Pointer-Events. In einem Rechteck über
 * Beschriftung und Wert erkennt der Server die Beschriftung als Anker; das Rechteck bleibt, wie gezeichnet,
 * und wird beim Anwenden an den gefundenen Anker verschoben. Kein innerHTML, keine Inline-Handler.
 */

import { __ } from "./i18n.js";
import { postJson } from "./lib/http.js";

/**
 * @typedef {{ x: number, y: number, w: number, h: number }} Rect
 * @typedef {{ page: number, x: number, y: number, w: number, h: number, text: string }} WordBox
 * @typedef {{ field: string, page: number, x: number, y: number, w: number, h: number, anchor?: string | null, anchor_box?: Rect | null, scope?: string, pattern?: string | null, source?: string, confirmed?: boolean }} Zone
 * @typedef {{ pages: { page: number, url: string }[], boxes: WordBox[], zones: Zone[], fields: Record<string, string>, scopes: Record<string, string>, previewUrl: string, trialUrl: string }} ZoneEditorConfig
 * @typedef {{ raw: string, value: string | null, anchor: string | null, anchor_box: Rect | null, x: number, y: number, w: number, h: number, scope: string, pattern: string | null, hits: number }} PreviewResult
 * @typedef {{ total: number, filled: number, rows: { title: string, fields: Record<string, string> }[] }} TrialResult
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
    const trialEl = /** @type {HTMLButtonElement | null} */ (root.querySelector("[data-zone-trial]"));
    const trialResultEl = /** @type {HTMLElement | null} */ (root.querySelector("[data-zone-trial-result]"));
    if (!pagesEl || !fieldEl || !listEl || !inputEl) return;

    /** @type {Zone[]} */
    let zones = cfg.zones.map((z) => ({ scope: z.anchor ? "any" : "page", ...z }));
    /** @type {Record<string, { value: string, hits: number }>} */
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

    /** @param {Zone} zone */
    const payload = ({ field, page, x, y, w, h, anchor, anchor_box, scope, pattern }) => ({
        field, page, x, y, w, h,
        anchor: anchor ?? null,
        anchor_box: anchor_box ?? null,
        scope: scope ?? (anchor ? "any" : "page"),
        pattern: pattern && pattern.trim() !== "" ? pattern.trim() : null,
    });

    const serialize = () => {
        inputEl.value = JSON.stringify(zones.map(payload));
    };

    /**
     * @param {string} tag
     * @param {string} className
     * @param {string} [text]
     */
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const renderList = () => {
        while (listEl.firstChild) listEl.removeChild(listEl.firstChild);
        for (const zone of zones) {
            const li = el("li", "space-y-1 rounded bg-base-200 px-2 py-1");
            const head = el("div", "flex items-center justify-between gap-2");
            const text = document.createElement("span");
            text.appendChild(el("strong", "", cfg.fields[zone.field] ?? zone.field));
            const current = values[zone.field];
            text.appendChild(document.createTextNode(": " + (current ? current.value : __("js.zone_editor.no_value"))));
            if (current && zone.anchor) {
                text.appendChild(el("span", "ml-1 text-xs text-muted", __("js.zone_editor.hits", { count: current.hits })));
            }
            if (zone.source === "learned" && !zone.confirmed) {
                text.appendChild(el("span", "ml-1 text-xs text-muted", __("js.zone_editor.learned")));
            }
            const remove = el("button", "btn btn-ghost btn-xs", __("js.zone_editor.remove"));
            remove.setAttribute("type", "button");
            remove.addEventListener("click", () => {
                zones = zones.filter((z) => z.field !== zone.field);
                delete values[zone.field];
                renderZones();
            });
            head.append(text, remove);
            li.appendChild(head);

            const anchorLine = el("label", "flex cursor-pointer items-center gap-2 text-xs text-muted");
            const anchorToggle = /** @type {HTMLInputElement} */ (el("input", "checkbox checkbox-xs"));
            anchorToggle.type = "checkbox";
            anchorToggle.checked = Boolean(zone.anchor);
            anchorToggle.addEventListener("change", () => {
                if (anchorToggle.checked) {
                    // Anker im gezeichneten Kästchen neu erkennen lassen.
                    zone.anchor = null;
                    zone.anchor_box = null;
                    void preview(zone, true);
                } else {
                    zone.anchor = null;
                    zone.anchor_box = null;
                    zone.scope = "page";
                    void preview(zone, false);
                }
            });
            anchorLine.appendChild(anchorToggle);
            anchorLine.appendChild(document.createTextNode(zone.anchor ? `${__("js.zone_editor.anchor")}: „${zone.anchor}“` : __("js.zone_editor.use_anchor")));
            li.appendChild(anchorLine);

            const controls = el("div", "flex flex-wrap items-center gap-2");
            const scopeLabel = el("label", "flex items-center gap-1 text-xs");
            scopeLabel.appendChild(document.createTextNode(__("js.zone_editor.scope")));
            const scopeSelect = /** @type {HTMLSelectElement} */ (el("select", "select select-bordered select-xs"));
            for (const [key, label] of Object.entries(cfg.scopes)) {
                const option = document.createElement("option");
                option.value = key;
                option.textContent = key === "page" ? `${label} (${__("js.zone_editor.page")} ${zone.page})` : label;
                option.selected = (zone.scope ?? "page") === key;
                scopeSelect.appendChild(option);
            }
            scopeSelect.addEventListener("change", () => {
                zone.scope = scopeSelect.value;
                void preview(zone, false);
            });
            scopeLabel.appendChild(scopeSelect);
            controls.appendChild(scopeLabel);

            const patternInput = /** @type {HTMLInputElement} */ (el("input", "input input-bordered input-xs w-48"));
            patternInput.type = "text";
            patternInput.placeholder = __("js.zone_editor.pattern");
            patternInput.value = zone.pattern ?? "";
            patternInput.maxLength = 200;
            patternInput.addEventListener("change", () => {
                zone.pattern = patternInput.value.trim() === "" ? null : patternInput.value.trim();
                void preview(zone, false);
            });
            controls.appendChild(patternInput);
            li.appendChild(controls);
            listEl.appendChild(li);
        }
        serialize();
    };

    const renderZones = () => {
        for (const [page, overlay] of overlays) {
            overlay.querySelectorAll("[data-zone-rect],[data-anchor-rect]").forEach((node) => node.remove());
            for (const zone of zones) {
                if (zone.page !== page) continue;
                const rect = el("div", "pointer-events-none absolute border-2 border-primary bg-primary/10" + (zone.source === "learned" && !zone.confirmed ? " border-dashed" : ""));
                rect.dataset.zoneRect = zone.field;
                place(rect, zone);
                const badge = el("span", "badge badge-primary badge-xs absolute -top-4 left-0 whitespace-nowrap", cfg.fields[zone.field] ?? zone.field);
                rect.appendChild(badge);
                overlay.appendChild(rect);
                if (zone.anchor && zone.anchor_box) {
                    const anchorRect = el("div", "pointer-events-none absolute border-2 border-dashed border-secondary bg-secondary/10");
                    anchorRect.dataset.anchorRect = zone.field;
                    anchorRect.title = zone.anchor;
                    place(anchorRect, zone.anchor_box);
                    overlay.appendChild(anchorRect);
                }
            }
        }
        renderList();
    };

    /**
     * Vorschau beim Server: mit `detect` trennt er Beschriftung und Wert, sonst wertet er die Zone wie gespeichert aus.
     *
     * @param {Zone} zone
     * @param {boolean} detect
     */
    const preview = async (zone, detect) => {
        const res = /** @type {import("./lib/http.js").JsonResult<PreviewResult>} */ (
            await postJson(cfg.previewUrl, { ...payload(zone), detect: detect ? 1 : 0 })
        );
        if (!res.ok || !res.data) {
            values[zone.field] = { value: res.status === 422 ? __("js.zone_editor.pattern_invalid") : __("js.zone_editor.preview_failed"), hits: 0 };
            renderList();
            return;
        }
        const data = res.data;
        if (detect) {
            zone.anchor = data.anchor;
            zone.anchor_box = data.anchor_box;
            zone.scope = data.scope;
        }
        values[zone.field] = { value: data.value ?? (data.raw ? `? ${data.raw}` : __("js.zone_editor.no_value")), hits: data.hits };
        renderZones();
    };

    const trial = async () => {
        if (!trialEl || !trialResultEl) return;
        serialize();
        trialEl.disabled = true;
        while (trialResultEl.firstChild) trialResultEl.removeChild(trialResultEl.firstChild);
        trialResultEl.appendChild(el("p", "text-sm text-muted", __("js.zone_editor.trial_running")));
        const res = /** @type {import("./lib/http.js").JsonResult<TrialResult>} */ (await postJson(cfg.trialUrl, { zones: inputEl.value }));
        trialEl.disabled = false;
        while (trialResultEl.firstChild) trialResultEl.removeChild(trialResultEl.firstChild);
        if (!res.ok || !res.data) {
            trialResultEl.appendChild(el("p", "text-sm text-error", __("js.zone_editor.trial_failed")));
            return;
        }
        const data = res.data;
        if (data.total === 0) {
            trialResultEl.appendChild(el("p", "text-sm text-muted", __("js.zone_editor.trial_none")));
            return;
        }
        trialResultEl.appendChild(el("p", "text-sm font-medium", __("js.zone_editor.trial_result", { filled: data.filled, total: data.total })));
        const ul = el("ul", "mt-1 max-h-64 space-y-1 overflow-y-auto text-xs");
        for (const row of data.rows) {
            const li = el("li", "rounded bg-base-200 px-2 py-1");
            li.appendChild(el("div", "truncate font-medium", row.title));
            const parts = Object.entries(row.fields).map(([field, value]) => `${cfg.fields[field] ?? field}: ${value}`);
            li.appendChild(el("div", "text-muted", parts.length > 0 ? parts.join(" · ") : __("js.zone_editor.no_value")));
            ul.appendChild(li);
        }
        trialResultEl.appendChild(ul);
    };

    const toggleBoxes = () => {
        const show = showBoxesEl ? showBoxesEl.checked : true;
        for (const overlay of overlays.values()) {
            overlay.querySelectorAll("[data-word-box]").forEach((node) => {
                /** @type {HTMLElement} */ (node).classList.toggle("hidden", !show);
            });
        }
    };

    for (const page of cfg.pages) {
        const wrap = el("div", "relative w-full overflow-hidden rounded-box border border-base-300 bg-base-200");
        const img = document.createElement("img");
        img.src = page.url;
        img.alt = `${__("js.zone_editor.page")} ${page.page}`;
        img.className = "block w-full";
        img.draggable = false;
        const overlay = el("div", "absolute inset-0 cursor-crosshair");
        for (const box of cfg.boxes) {
            if (box.page !== page.page) continue;
            const node = el("div", "pointer-events-none absolute border border-info/40");
            node.dataset.wordBox = "";
            node.title = box.text;
            place(node, box);
            overlay.appendChild(node);
        }
        const draftEl = el("div", "pointer-events-none absolute hidden border-2 border-secondary bg-secondary/10");
        overlay.appendChild(draftEl);
        const pageBadge = el("span", "badge badge-ghost badge-sm absolute right-2 top-2", `${__("js.zone_editor.page")} ${page.page}`);
        overlay.appendChild(pageBadge);

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
                anchor: null,
                anchor_box: null,
                scope: "page",
                pattern: null,
                source: "manual",
                confirmed: true,
            });
            draft = null;
            draftEl.classList.add("hidden");
            // Ein Klick ohne Ziehen ist keine Zone.
            if (zone.w < 0.005 || zone.h < 0.003) return;
            zones = [...zones.filter((z) => z.field !== zone.field), zone];
            renderZones();
            void preview(zone, true);
        });
        wrap.append(img, overlay);
        pagesEl.appendChild(wrap);
        overlays.set(page.page, overlay);
    }

    showBoxesEl?.addEventListener("change", toggleBoxes);
    trialEl?.addEventListener("click", () => void trial());
    root.addEventListener("submit", serialize);
    renderZones();
    for (const zone of zones) void preview(zone, false);
};

const initAll = (scope = document) => {
    /** @type {NodeListOf<HTMLFormElement>} */ (scope.querySelectorAll("form[data-zone-editor]")).forEach((root) => init(root));
};

document.addEventListener("DOMContentLoaded", () => initAll());
document.addEventListener("livewire:navigated", () => initAll());

export { init, initAll };

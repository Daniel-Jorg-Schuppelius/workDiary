/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guide-editor.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Bildanleitungen der Wissensdatenbank (Feature 191, MVP-1668).
 *
 * Anzeige: `[data-guide-image]` mit `data-markers` (JSON) — die Markierungen
 * werden auf ein Canvas über dem Bild gezeichnet; Nummern und Texte stehen
 * zusätzlich als Liste unter dem Bild.
 *
 * Editor: `form[data-guide-editor]` — Werkzeug wählen (Pfeil, Nummer, Rahmen,
 * Text, Schwärzen), auf dem Bild ziehen bzw. klicken. Markierungen und
 * Schwärzungen gehen beim Absenden als JSON in die versteckten Felder; das
 * Schwärzen führt der Server aus und verwirft dabei das Original. Kein
 * innerHTML, keine Inline-Handler, Zeichnen über Pointer Events.
 */

import { arrowHead, markerFromGesture, rectFromPoints } from "./lib/guide-markers.js";

/**
 * @typedef {import("./lib/guide-markers.js").GuideMarker} GuideMarker
 * @typedef {import("./lib/guide-markers.js").GuideRect} GuideRect
 */

const COLOR = "#dc2626";

/**
 * @param {string | undefined} json
 * @returns {GuideMarker[]}
 */
const parseMarkers = (json) => {
    try {
        const value = JSON.parse(json || "[]");
        return Array.isArray(value) ? value.filter((m) => m && typeof m === "object") : [];
    } catch {
        return [];
    }
};

/**
 * @param {HTMLCanvasElement} canvas
 * @param {HTMLImageElement} img
 * @param {GuideMarker[]} markers
 * @param {GuideRect[]} redactions
 * @param {{ tool: string, rect: GuideRect | null, arrow: { x: number, y: number, x2: number, y2: number } | null } | null} draft
 */
const draw = (canvas, img, markers, redactions, draft) => {
    const width = img.clientWidth;
    const height = img.clientHeight;
    if (width === 0 || height === 0) return;
    const ratio = window.devicePixelRatio || 1;
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, width, height);
    const stroke = Math.max(2, width / 300);
    const font = Math.max(12, width / 60);
    ctx.lineWidth = stroke;
    ctx.strokeStyle = COLOR;
    ctx.fillStyle = COLOR;
    ctx.font = `bold ${font}px sans-serif`;
    ctx.textBaseline = "middle";

    for (const rect of redactions) {
        ctx.fillStyle = "rgba(0, 0, 0, 0.85)";
        ctx.fillRect(rect.x * width, rect.y * height, rect.w * width, rect.h * height);
    }
    for (const marker of markers) {
        const x = marker.x * width;
        const y = marker.y * height;
        ctx.strokeStyle = COLOR;
        ctx.fillStyle = COLOR;
        if (marker.kind === "frame") {
            ctx.strokeRect(x, y, (marker.w ?? 0) * width, (marker.h ?? 0) * height);
        } else if (marker.kind === "arrow") {
            const x2 = (marker.x2 ?? marker.x) * width;
            const y2 = (marker.y2 ?? marker.y) * height;
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            const head = arrowHead(x, y, x2, y2, 6 * stroke + 8);
            ctx.beginPath();
            ctx.moveTo(head[0], head[1]);
            ctx.lineTo(head[2], head[3]);
            ctx.lineTo(head[4], head[5]);
            ctx.closePath();
            ctx.fill();
        } else if (marker.kind === "number") {
            const radius = font * 0.9;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, 2 * Math.PI);
            ctx.fill();
            ctx.fillStyle = "#ffffff";
            ctx.textAlign = "center";
            ctx.fillText(String(marker.label ?? ""), x, y);
        } else if (marker.kind === "text") {
            const label = String(marker.label ?? "");
            const padding = font / 2;
            const boxWidth = ctx.measureText(label).width + 2 * padding;
            ctx.fillStyle = "#ffffff";
            ctx.fillRect(x, y, boxWidth, font + 2 * padding);
            ctx.strokeRect(x, y, boxWidth, font + 2 * padding);
            ctx.fillStyle = "#111827";
            ctx.textAlign = "left";
            ctx.fillText(label, x + padding, y + padding + font / 2);
        }
    }
    if (draft?.rect) {
        ctx.setLineDash([6, 4]);
        ctx.strokeStyle = draft.tool === "redact" ? "#000000" : COLOR;
        ctx.strokeRect(draft.rect.x * width, draft.rect.y * height, draft.rect.w * width, draft.rect.h * height);
        ctx.setLineDash([]);
    }
    if (draft?.arrow) {
        ctx.beginPath();
        ctx.moveTo(draft.arrow.x * width, draft.arrow.y * height);
        ctx.lineTo(draft.arrow.x2 * width, draft.arrow.y2 * height);
        ctx.stroke();
    }
};

/** @param {HTMLElement} root */
const initDisplay = (root) => {
    if (root.dataset.guideBound === "1" || root.hasAttribute("data-guide-stage")) return;
    root.dataset.guideBound = "1";
    const img = /** @type {HTMLImageElement | null} */ (root.querySelector("[data-guide-picture]"));
    const canvas = /** @type {HTMLCanvasElement | null} */ (root.querySelector("[data-guide-canvas]"));
    if (!img || !canvas) return;
    const markers = parseMarkers(root.dataset.markers);
    const render = () => draw(canvas, img, markers, [], null);
    if (img.complete) render();
    img.addEventListener("load", render);
    new ResizeObserver(render).observe(img);
};

/** @param {HTMLFormElement} form */
const initEditor = (form) => {
    if (form.dataset.guideBound === "1") return;
    form.dataset.guideBound = "1";
    const stage = /** @type {HTMLElement | null} */ (form.querySelector("[data-guide-stage]"));
    const img = /** @type {HTMLImageElement | null} */ (stage?.querySelector("[data-guide-picture]") ?? null);
    const canvas = /** @type {HTMLCanvasElement | null} */ (stage?.querySelector("[data-guide-canvas]") ?? null);
    const markersInput = /** @type {HTMLInputElement | null} */ (form.querySelector("[data-guide-markers-input]"));
    const redactionsInput = /** @type {HTMLInputElement | null} */ (form.querySelector("[data-guide-redactions-input]"));
    const labelInput = /** @type {HTMLInputElement | null} */ (form.querySelector("[data-guide-label]"));
    const list = /** @type {HTMLElement | null} */ (form.querySelector("[data-guide-marker-list]"));
    const empty = /** @type {HTMLElement | null} */ (form.querySelector("[data-guide-marker-empty]"));
    if (!stage || !img || !canvas || !markersInput || !redactionsInput) return;

    /** @type {Record<string, string>} */
    let names = {};
    try {
        names = JSON.parse(form.dataset.markerNames || "{}");
    } catch {
        names = {};
    }
    /** @type {GuideMarker[]} */
    let markers = parseMarkers(markersInput.value);
    /** @type {GuideRect[]} */
    let redactions = [];
    /** @type {{ x: number, y: number } | null} */
    let start = null;
    /** @type {{ tool: string, rect: GuideRect | null, arrow: { x: number, y: number, x2: number, y2: number } | null } | null} */
    let draft = null;

    const tool = () => {
        const checked = /** @type {HTMLInputElement | null} */ (form.querySelector('input[name="tool"]:checked'));
        return checked ? checked.value : "arrow";
    };
    const render = () => draw(canvas, img, markers, redactions, draft);

    const renderList = () => {
        if (!list) return;
        list.replaceChildren();
        /** @type {{ label: string, remove: () => void }[]} */
        const rows = [
            ...markers.map((marker, index) => ({
                label: `${names[marker.kind] ?? marker.kind}${marker.label ? `: ${marker.label}` : ""}`,
                remove: () => { markers = markers.filter((_, i) => i !== index); },
            })),
            ...redactions.map((_, index) => ({
                label: form.dataset.labelRedaction ?? "",
                remove: () => { redactions = redactions.filter((__, i) => i !== index); },
            })),
        ];
        for (const row of rows) {
            const item = document.createElement("li");
            item.className = "flex items-center justify-between gap-2";
            const text = document.createElement("span");
            text.textContent = row.label;
            const button = document.createElement("button");
            button.type = "button";
            button.className = "btn btn-ghost btn-xs";
            button.textContent = "✕";
            button.setAttribute("aria-label", form.dataset.labelRemove ?? "");
            button.title = form.dataset.labelRemove ?? "";
            button.addEventListener("click", () => {
                row.remove();
                renderList();
                render();
            });
            item.append(text, button);
            list.appendChild(item);
        }
        if (empty) empty.hidden = rows.length > 0;
    };

    /** @param {PointerEvent} event */
    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        return {
            x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
            y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
        };
    };

    canvas.addEventListener("pointerdown", (event) => {
        start = point(event);
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener("pointermove", (event) => {
        if (!start) return;
        const current = point(event);
        const active = tool();
        draft = active === "arrow"
            ? { tool: active, rect: null, arrow: { x: start.x, y: start.y, x2: current.x, y2: current.y } }
            : (active === "frame" || active === "redact")
                ? { tool: active, rect: rectFromPoints(start.x, start.y, current.x, current.y), arrow: null }
                : null;
        render();
    });
    canvas.addEventListener("pointerup", (event) => {
        if (!start) return;
        const end = point(event);
        const active = tool();
        if (active === "redact") {
            const rect = rectFromPoints(start.x, start.y, end.x, end.y);
            if (rect) redactions = [...redactions, rect];
        } else {
            const marker = markerFromGesture(active, start, end, labelInput?.value ?? "", markers);
            if (marker) {
                markers = [...markers, marker];
            } else if (active === "text" && form.dataset.labelPrompt && typeof window.notifyAction === "function") {
                window.notifyAction({ tone: "warning", message: form.dataset.labelPrompt });
            }
        }
        start = null;
        draft = null;
        renderList();
        render();
    });

    form.addEventListener("submit", () => {
        markersInput.value = JSON.stringify(markers);
        redactionsInput.value = JSON.stringify(redactions);
    });

    if (img.complete) render();
    img.addEventListener("load", render);
    new ResizeObserver(render).observe(img);
    renderList();
};

const initAll = (scope = document) => {
    /** @type {NodeListOf<HTMLFormElement>} */ (scope.querySelectorAll("form[data-guide-editor]")).forEach((form) => initEditor(form));
    /** @type {NodeListOf<HTMLElement>} */ (scope.querySelectorAll("[data-guide-image]")).forEach((root) => initDisplay(root));
};

document.addEventListener("DOMContentLoaded", () => initAll());

export { initAll };

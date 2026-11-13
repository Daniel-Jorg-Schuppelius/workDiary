/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guide-markers.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Geometrie der Markierungen einer Bildanleitung (Feature 191, MVP-1668) ohne
 * DOM: Koordinaten relativ zum Bild (0–1), Rechteck aus zwei Punkten,
 * Pfeilspitze, nächste freie Nummer. Prüfbar unter node:test; Zeichnen und
 * Bedienung liegen in resources/js/guide-editor.js. Der Server normalisiert
 * dieselben Werte noch einmal (KnowledgeGuideService).
 */

/**
 * @typedef {{ kind: "arrow" | "number" | "frame" | "text", x: number, y: number, x2?: number, y2?: number, w?: number, h?: number, label?: string }} GuideMarker
 * @typedef {{ x: number, y: number, w: number, h: number }} GuideRect
 */

/** Kleinste Ausdehnung, ab der ein Zug als Rechteck bzw. Pfeil zählt (relativ). */
export const MIN_EXTENT = 0.01;

/**
 * @param {number} value
 * @returns {number}
 */
export function clamp01(value) {
    if (!Number.isFinite(value)) return 0;
    return Math.round(Math.min(1, Math.max(0, value)) * 10000) / 10000;
}

/**
 * Rechteck aus Start- und Endpunkt eines Zugs, unabhängig von der Zugrichtung.
 *
 * @param {number} x0
 * @param {number} y0
 * @param {number} x1
 * @param {number} y1
 * @returns {GuideRect | null} null, wenn der Zug zu klein ist (Klick)
 */
export function rectFromPoints(x0, y0, x1, y1) {
    const left = clamp01(Math.min(x0, x1));
    const top = clamp01(Math.min(y0, y1));
    const w = clamp01(Math.abs(x1 - x0));
    const h = clamp01(Math.abs(y1 - y0));
    if (w < MIN_EXTENT || h < MIN_EXTENT) return null;
    return { x: left, y: top, w: Math.min(w, 1 - left), h: Math.min(h, 1 - top) };
}

/**
 * Nächste Nummer für eine Nummern-Markierung: eins über der höchsten Zahl.
 *
 * @param {GuideMarker[]} markers
 * @returns {string}
 */
export function nextNumber(markers) {
    let max = 0;
    for (const marker of markers) {
        if (marker.kind !== "number") continue;
        const value = Number.parseInt(String(marker.label ?? ""), 10);
        if (Number.isFinite(value) && value > max) max = value;
    }
    return String(max + 1);
}

/**
 * Eckpunkte der Pfeilspitze in Pixeln (Spitze, linke, rechte Ecke).
 *
 * @param {number} x1 Startpunkt
 * @param {number} y1
 * @param {number} x2 Spitze
 * @param {number} y2
 * @param {number} size Länge der Spitze
 * @returns {[number, number, number, number, number, number]}
 */
export function arrowHead(x1, y1, x2, y2, size) {
    const angle = Math.atan2(y2 - y1, x2 - x1);
    const spread = Math.PI / 7;
    return [
        x2, y2,
        x2 - size * Math.cos(angle - spread), y2 - size * Math.sin(angle - spread),
        x2 - size * Math.cos(angle + spread), y2 - size * Math.sin(angle + spread),
    ];
}

/**
 * Markierung aus einem Zug bzw. Klick; null, wenn sie nicht entsteht.
 *
 * @param {string} tool arrow | number | frame | text
 * @param {{ x: number, y: number }} start
 * @param {{ x: number, y: number }} end
 * @param {string} label eingegebene Beschriftung
 * @param {GuideMarker[]} existing vorhandene Markierungen (für die Nummer)
 * @returns {GuideMarker | null}
 */
export function markerFromGesture(tool, start, end, label, existing) {
    const text = String(label ?? "").trim().slice(0, 120);
    if (tool === "frame") {
        const rect = rectFromPoints(start.x, start.y, end.x, end.y);
        return rect ? { kind: "frame", ...rect } : null;
    }
    if (tool === "arrow") {
        if (Math.hypot(end.x - start.x, end.y - start.y) < MIN_EXTENT) return null;
        return { kind: "arrow", x: clamp01(start.x), y: clamp01(start.y), x2: clamp01(end.x), y2: clamp01(end.y) };
    }
    if (tool === "number") {
        return { kind: "number", x: clamp01(end.x), y: clamp01(end.y), label: text !== "" ? text : nextNumber(existing) };
    }
    if (tool === "text") {
        return text !== "" ? { kind: "text", x: clamp01(end.x), y: clamp01(end.y), label: text } : null;
    }
    return null;
}

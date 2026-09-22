/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dialog-drag.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Pure Logik für verschiebbare Dialoge — ohne DOM, damit sie unter node:test
 * prüfbar ist. Die Verdrahtung (Pointer-Events auf .wd-dialog__header)
 * liegt in resources/js/app.js.
 */

/**
 * Elemente im Dialog-Header, auf denen kein Drag beginnt.
 * `[data-no-drag]` erlaubt Aufrufern, weitere Bereiche auszunehmen.
 */
export const DRAG_IGNORE_SELECTOR =
    "button, a, input, select, textarea, label, [contenteditable], .wd-dialog__header-actions, [data-no-drag]";

/** Mindestbreite bzw. -höhe der Box, die im Viewport sichtbar bleiben muss. */
export const DRAG_VISIBLE_MIN = 48;

/** Unterhalb dieser Breite gilt das Bottom-Sheet-Layout — dort wird nicht verschoben. */
export const DRAG_MEDIA_QUERY = "(min-width: 640px)";

/**
 * @typedef {{ x: number, y: number }} Offset
 * @typedef {{ left: number, top: number, width: number, height: number }} Rect
 * @typedef {{ width: number, height: number }} Size
 */

/**
 * Prüft, ob ein Pointerdown auf `target` einen Drag starten darf: nur auf
 * freier Header-Fläche, nicht auf Bedienelementen innerhalb des Headers.
 *
 * @param {{ closest(selector: string): any }} target Element unter dem Zeiger
 * @param {{ contains(node: any): boolean }} header Der Dialog-Header
 * @returns {boolean}
 */
export function isDragBlocked(target, header) {
    const blocked = target.closest(DRAG_IGNORE_SELECTOR);
    return blocked != null && header.contains(blocked);
}

/**
 * Begrenzt eine gewünschte Verschiebung so, dass die Box im Viewport bleibt:
 * Der obere Rand (Header) darf nie über den Viewport hinaus, unten und seitlich
 * bleiben mindestens `visibleMin` Pixel sichtbar.
 *
 * @param {Offset} offset Gewünschte Verschiebung relativ zur Ausgangslage
 * @param {Rect} box Ausgangs-Rechteck der Box ohne Verschiebung
 * @param {Size} viewport
 * @param {number} [visibleMin]
 * @returns {Offset}
 */
export function clampOffset(offset, box, viewport, visibleMin = DRAG_VISIBLE_MIN) {
    const minX = visibleMin - box.width - box.left;
    const maxX = viewport.width - visibleMin - box.left;
    const minY = -box.top;
    const maxY = viewport.height - visibleMin - box.top;

    // `|| 0` normalisiert -0 (z. B. aus -box.top bei top = 0).
    return {
        x: Math.round(Math.min(Math.max(offset.x, minX), Math.max(minX, maxX))) || 0,
        y: Math.round(Math.min(Math.max(offset.y, minY), Math.max(minY, maxY))) || 0,
    };
}

/**
 * Verschiebung aus Zeigerbewegung ableiten: Basis-Offset (falls die Box in
 * derselben Sitzung schon verschoben war) plus Delta seit Drag-Start, begrenzt
 * auf den Viewport.
 *
 * @param {{ start: Offset, current: Offset, base: Offset, box: Rect, viewport: Size }} drag
 * @returns {Offset}
 */
export function dragOffset({ start, current, base, box, viewport }) {
    return clampOffset(
        { x: base.x + current.x - start.x, y: base.y + current.y - start.y },
        box,
        viewport,
    );
}

/**
 * Ausgangs-Rechteck der Box ohne die bereits angewendete Verschiebung.
 * `getBoundingClientRect()` liefert die verschobene Lage, für das Clamping
 * braucht es aber die zentrierte Ausgangsposition.
 *
 * @param {Rect} rect Gemessenes Rechteck (verschoben)
 * @param {Offset} applied Aktuell angewendete Verschiebung
 * @returns {Rect}
 */
export function baseRect(rect, applied) {
    return {
        left: rect.left - applied.x,
        top: rect.top - applied.y,
        width: rect.width,
        height: rect.height,
    };
}

/**
 * CSS-Wert für die `translate`-Eigenschaft.
 *
 * @param {Offset} offset
 * @returns {string}
 */
export function translateValue(offset) {
    return `${offset.x}px ${offset.y}px`;
}
